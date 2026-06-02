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
require_once __DIR__ . '/../includes/JobHandlers.php';

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

// Мапінг типів задач на обробники
$handlers = [
    Queue::TYPE_MODERATION_CHECK   => [JobHandlers::class, 'handleModerationCheck'],
    Queue::TYPE_MODERATION_REWRITE => [JobHandlers::class, 'handleModerationRewrite'],
    Queue::TYPE_EMAIL_VERIFY       => [JobHandlers::class, 'handleEmailVerify'],
    Queue::TYPE_EMAIL_CHANGED     => [JobHandlers::class, 'handleEmailChanged'],
    Queue::TYPE_EMAIL_CUSTOM      => [JobHandlers::class, 'handleEmailCustom'],
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
            call_user_func($handler, $job['payload'], 'workerLog');
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
