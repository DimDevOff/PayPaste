<?php
/**
 * Уніфікований Worker для фонової обробки задач з черги.
 * Замінює cron/ai_worker.php, забезпечує retry, timeout, логування.
 *
 * Запуск:
 *   php cron/worker.php              — обробити один батч і вийти
 *   php cron/worker.php --daemon     — працювати безперервно (для VPS)
 *   php cron/worker.php --type=email — обробляти лише email-задачі
 *
 * Рекомендується додати в crontab (кожну хвилину):
 *   * * * * * php /path/to/cron/worker.php >> /path/to/data/logs/worker.log 2>&1
 */

// Блокуємо прямий веб-доступ — cron-скрипти не повинні виконуватись через браузер
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Доступ заборонено. Цей скрипт призначений лише для CLI.');
}

// Worker не потребує сесій, CSRF, кукі — працює в CLI
define('NO_SESSION', true);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/Queue.php';
require_once __DIR__ . '/../includes/Moderation.php';
require_once __DIR__ . '/../includes/mailer.php';

set_time_limit(300); // 5 хвилин на батч
ini_set('max_execution_time', '300');

// Конфігурація
$DAEMON_MODE = in_array('--daemon', $argv ?? []);
$FILTER_TYPE = null;

// Фільтр за типом задачі
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--type=') === 0) {
        $FILTER_TYPE = substr($arg, 7);
    }
}

// Валідація типу задачі за білим списком (безпека)
if ($FILTER_TYPE !== null && !in_array($FILTER_TYPE, Queue::getTypes(), true)) {
    fwrite(STDERR, "Помилка: Неприпустимий тип воркера: '$FILTER_TYPE'. Дозволені типи: " . implode(', ', Queue::getTypes()) . "\n");
    exit(1);
}

$BATCH_SIZE  = 10;
$SLEEP_BETWEEN = 2;      // Секунд між батчами в daemon-режимі
$CURL_TIMEOUT = 30;       // Таймаут для зовнішніх API-викликів

// Лог-директорія
$logDir = __DIR__ . '/../data/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0700, true);
    chmod($logDir, 0700);
}

/**
 * Логування подій worker-а.
 */
function workerLog(string $msg): void {
    global $logDir;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $msg\n";
    $logFile = $logDir . '/worker.log';
    $isNewLog = !file_exists($logFile);
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    if ($isNewLog) {
        chmod($logFile, 0600);
    }
}

// Обробники задач

/**
 * Обробка задачі модерації (OpenAI Moderation API).
 * Оновлює moderation_status пасти залежно від результату.
 */
function handleModerationCheck(array $payload): void {
    $pasteId = $payload['paste_id'] ?? null;
    $content = $payload['content'] ?? '';

    if (!$pasteId) {
        throw new \InvalidArgumentException('Відсутній paste_id у payload');
    }

    $pdo = DB::getInstance()->getPDO();

    // Перевіряємо, що паста ще існує
    $stmt = $pdo->prepare("SELECT id, moderation_status FROM pastes WHERE id = ?");
    $stmt->execute([$pasteId]);
    $paste = $stmt->fetch();

    if (!$paste) {
        workerLog("moderation_check: пасту $pasteId не знайдено (можливо видалено) — пропускаємо");
        return;
    }

    // Перевіряємо, чи не оброблено вже (ідемпотентність)
    if ($paste['moderation_status'] !== 'pending') {
        workerLog("moderation_check: паста $pasteId вже має статус '{$paste['moderation_status']}' — пропускаємо");
        return;
    }

    // Перевіряємо через OpenAI (без локальної перевірки — вона вже пройшла синхронно)
    $violations = Moderation::checkExternal($content);

    if ($violations === false) {
        // OpenAI не знайшов порушень → approved
        $stmt = $pdo->prepare("UPDATE pastes SET moderation_status = 'approved' WHERE id = ?");
        $stmt->execute([$pasteId]);
        workerLog("moderation_check: паста $pasteId APPROVED (OpenAI чисто)");
    } else {
        // Знайдено порушення → rejected, зберігаємо причини
        $stmt = $pdo->prepare("UPDATE pastes SET moderation_status = 'rejected', moderation_result = ? WHERE id = ?");
        $stmt->execute([json_encode($violations, JSON_UNESCAPED_UNICODE), $pasteId]);
        workerLog("moderation_check: паста $pasteId REJECTED: " . implode(', ', $violations));
    }
}

/**
 * Обробка задачі AI-перефразування (Ollama).
 */
function handleModerationRewrite(array $payload): void {
    $pasteId = $payload['paste_id'] ?? null;
    $content = $payload['content'] ?? '';

    if (!$pasteId) {
        throw new \InvalidArgumentException('Відсутній paste_id у payload');
    }

    $pdo = DB::getInstance()->getPDO();

    $stmt = $pdo->prepare("SELECT id, content FROM pastes WHERE id = ? AND is_pending_rewrite = 1");
    $stmt->execute([$pasteId]);
    $paste = $stmt->fetch();

    if (!$paste) {
        workerLog("moderation_rewrite: пасту $pasteId не знайдено або вже оброблено — пропускаємо");
        return;
    }

    // Перефразування через Ollama (використовуємо контент з БД, актуальніший)
    $rewritten = Moderation::rewrite($paste['content']);

    // Повторна модерація результату AI-переписування — не довіряємо автогенерованому
    // контенту без перевірки, оскільки prompt injection може призвести до небажаного результату.
    $localViolations = Moderation::localCheck($rewritten);
    if ($localViolations) {
        // Локальна перевірка знайшла порушення — відхиляємо переписаний текст
        $updateStmt = $pdo->prepare("
            UPDATE pastes
            SET is_pending_rewrite = 0, moderation_status = 'rejected', moderation_result = ?
            WHERE id = ?
        ");
        $updateStmt->execute([json_encode($localViolations, JSON_UNESCAPED_UNICODE), $pasteId]);
        workerLog("moderation_rewrite: паста $pasteId REJECTED після переписування (локальна перевірка): " . implode(', ', $localViolations));
        return;
    }

    // Зовнішня перевірка переписаного тексту через OpenAI
    try {
        $externalViolations = Moderation::checkExternal($rewritten);
    } catch (\Throwable $e) {
        // Якщо зовнішня перевірка недоступна — зберігаємо переписаний текст
        // і ставимо у чергу на стандартну модерацію (moderation_check)
        $updateStmt = $pdo->prepare("
            UPDATE pastes
            SET content = ?, is_pending_rewrite = 0, moderation_status = 'pending'
            WHERE id = ?
        ");
        $updateStmt->execute([$rewritten, $pasteId]);

        try {
            Queue::push(
                Queue::TYPE_MODERATION_CHECK,
                [
                    'paste_id' => $pasteId,
                    'content'  => $rewritten
                ],
                'mod_check:' . $pasteId
            );
            workerLog("moderation_rewrite: паста $pasteId переписана, зовнішня перевірка недоступна — поставлено у чергу moderation_check");
        } catch (\Throwable $pushErr) {
            workerLog("moderation_rewrite: паста $pasteId переписана, але не вдалося поставити moderation_check у чергу: " . $pushErr->getMessage());
        }
        return;
    }

    if ($externalViolations) {
        // Зовнішня перевірка знайшла порушення — відхиляємо
        $updateStmt = $pdo->prepare("
            UPDATE pastes
            SET is_pending_rewrite = 0, moderation_status = 'rejected', moderation_result = ?
            WHERE id = ?
        ");
        $updateStmt->execute([json_encode($externalViolations, JSON_UNESCAPED_UNICODE), $pasteId]);
        workerLog("moderation_rewrite: паста $pasteId REJECTED після переписування (OpenAI): " . implode(', ', $externalViolations));
        return;
    }

    // Усі перевірки пройдені — схвалюємо переписаний контент
    $updateStmt = $pdo->prepare("
        UPDATE pastes
        SET content = ?, is_pending_rewrite = 0, moderation_status = 'approved'
        WHERE id = ?
    ");
    $updateStmt->execute([$rewritten, $pasteId]);

    // Оновлюємо теги
    $pasteObj = Paste::findById($pasteId);
    if ($pasteObj) {
        $pasteObj->syncTags();
    }

    workerLog("moderation_rewrite: паста $pasteId перефразована, пройшла повторну модерацію — approved");
}

/**
 * Обробка задачі відправки email верифікації.
 */
function handleEmailVerify(array $payload): void {
    $to   = $payload['to'] ?? '';
    $code = $payload['code'] ?? '';

    if (empty($to) || empty($code)) {
        throw new \InvalidArgumentException('Відсутній to/code у payload email_verify');
    }

    $template = file_get_contents(__DIR__ . '/../templates/email_verify.html');
    $html = str_replace('{{CODE}}', htmlspecialchars($code), $template);

    $result = Mailer::sendDirect($to, 'Підтвердження пошти — PayPaste', $html);

    if (!$result) {
        throw new \RuntimeException("Не вдалося відправити верифікаційний лист на $to");
    }

    workerLog("email_verify: лист надіслано на $to");
}

/**
 * Обробка задачі повідомлення про зміну email.
 */
function handleEmailChanged(array $payload): void {
    $oldEmail = $payload['old_email'] ?? '';
    $newEmail = $payload['new_email'] ?? '';

    if (empty($oldEmail) || empty($newEmail)) {
        throw new \InvalidArgumentException('Відсутній old_email/new_email у payload');
    }

    $template = file_get_contents(__DIR__ . '/../templates/email_changed.html');
    $html = str_replace('{{NEW_EMAIL}}', htmlspecialchars($newEmail), $template);

    $result = Mailer::sendDirect($oldEmail, 'Зміна email-адреси — PayPaste', $html);

    if (!$result) {
        throw new \RuntimeException("Не вдалося відправити повідомлення про зміну email на $oldEmail");
    }

    workerLog("email_changed: повідомлення надіслано на $oldEmail");
}

/**
 * Обробка довільного email-повідомлення.
 */
function handleEmailCustom(array $payload): void {
    $to      = $payload['to'] ?? '';
    $subject = $payload['subject'] ?? '';
    $html    = $payload['html'] ?? '';

    if (empty($to) || empty($subject)) {
        throw new \InvalidArgumentException('Відсутній to/subject у payload email_custom');
    }

    $result = Mailer::sendDirect($to, $subject, $html);

    if (!$result) {
        throw new \RuntimeException("Не вдалося відправити лист на $to");
    }

    workerLog("email_custom: лист надіслано на $to");
}

// Мапінг типів задач на обробники
$handlers = [
    Queue::TYPE_MODERATION_CHECK   => 'handleModerationCheck',
    Queue::TYPE_MODERATION_REWRITE => 'handleModerationRewrite',
    Queue::TYPE_EMAIL_VERIFY       => 'handleEmailVerify',
    Queue::TYPE_EMAIL_CHANGED     => 'handleEmailChanged',
    Queue::TYPE_EMAIL_CUSTOM      => 'handleEmailCustom',
];

// Головний цикл обробки
workerLog("=== Worker запущено " . ($DAEMON_MODE ? '(daemon)' : '(single)') .
    ($FILTER_TYPE ? " [filter=$FILTER_TYPE]" : '') . " ===");

$iterations = 0;

do {
    $jobs = Queue::pop($BATCH_SIZE, $FILTER_TYPE);

    if (empty($jobs)) {
        if (!$DAEMON_MODE) {
            // Нічого обробляти — виходимо
            break;
        }
        sleep($SLEEP_BETWEEN);
        continue;
    }

    foreach ($jobs as $job) {
        $jobId = $job['id'];
        $type  = $job['type'];

        workerLog("Обробка $type [$jobId] (спроба {$job['attempts']})");

        if (!isset($handlers[$type])) {
            workerLog("Невідомий тип задачі: $type — позначено як dead");
            Queue::fail($jobId, "Невідомий тип задачі: $type");
            continue;
        }

        $handler = $handlers[$type];

        try {
            call_user_func($handler, $job['payload']);
            Queue::complete($jobId);
            workerLog("Завершено $type [$jobId]");
        } catch (\Throwable $e) {
            $errorMsg = get_class($e) . ': ' . $e->getMessage();
            workerLog("Помилка $type [$jobId]: $errorMsg");
            Queue::fail($jobId, $errorMsg);
        }
    }

    $iterations++;

    if ($DAEMON_MODE) {
        sleep($SLEEP_BETWEEN);
    }
} while ($DAEMON_MODE);

workerLog("Worker зупинено (оброблено ітерацій: $iterations)");
