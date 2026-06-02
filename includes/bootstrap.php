<?php
/**
 * Bootstrap-файл для ініціалізації додатку.
 * Централізує управління сесіями та загальні підключення.
 */

// ─── Production: приховуємо помилки, логуємо в syslog ─────────────────────
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e) {
    error_log('[PayPaste FATAL] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n");
        exit(1);
    }
    require __DIR__ . '/../templates/500.php';
    exit;
});

// ─── HTTPS примусово — ВИМКНЕНО, поки не налаштовано SSL на сервері ──────
// Редирект HTTP→HTTPS потрібно робити через Nginx, а не через PHP.
// Приклад Nginx-конфігу: ops/nginx.example.conf (return 301 https://...)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/repositories/Repo.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Paste.php';
require_once __DIR__ . '/models/AuditLog.php';
require_once __DIR__ . '/Queue.php';

// Сесії та CSRF потрібні лише для веб-запитів (не для CLI worker-а)
if (!defined('NO_SESSION')) {
    require_once __DIR__ . '/csrf.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Отримати поточного авторизованого користувача.
 * @return User|null
 */
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        // Перевірка на __PHP_Incomplete_Class
        if (isset($_SESSION['_user_cache']) && !($_SESSION['_user_cache'] instanceof User)) {
            unset($_SESSION['_user_cache']);
        }
        if (isset($_SESSION['_user_cache'])) {
            // Оновлюємо тільки баланс, щоб він завжди був актуальним (1 раз за запит)
            static $balance_updated = false;
            if (!$balance_updated) {
                $pdo = DB::getInstance()->getPDO();
                $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $res = $stmt->fetchColumn();
                if ($res !== false) {
                    $_SESSION['_user_cache']->credits = (int)$res;
                }
                $balance_updated = true;
            }
            return $_SESSION['_user_cache'];
        }
        if (!isset($_SESSION['_user_cache'])) {
            $user = User::findById($_SESSION['user_id']);
            if ($user) {
                $user->password_hash = null; // Не зберігаємо хеш пароля в сесії для безпеки
            }
            $_SESSION['_user_cache'] = $user;
        }
        return $_SESSION['_user_cache'];
    }
    return null;
}

/**
 * Перенаправлення на вказану локальну адресу.
 * @param string $location Локальний шлях (напр. 'index.php')
 */
function redirect($location) {
    // Захист від open redirect — дозволено лише відносні шляхи
    if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://') || str_starts_with($location, '//')) {
        $location = 'index.php';
    }
    header("Location: $location");
    exit;
}

// Глобальна перевірка верифікації пошти (тільки для веб-запитів)
if (!defined('NO_SESSION')) {
    $current_page = basename($_SERVER['PHP_SELF']);
    $allowed_unverified_pages = ['verify.php', 'login.php'];

    if (isset($_SESSION['user_id'])) {
        $user = getCurrentUser();
        if ($user && !$user->email_verified && !in_array($current_page, $allowed_unverified_pages)) {
            redirect('verify.php');
        }
    }
}

// Лінива обробка черги (inline worker / fallback)
// Якщо постійний worker запущений, цей блок майже ніколи не спрацює,
// бо черга вже обробляється у фоновому режимі.
//
// Це fallback на випадок якщо daemon впав або ще не запущений.
// За замовчуванням вимкнено, щоб веб-запити не чекали зовнішні API.
//
// Для тимчасового fallback можна встановити QUEUE_INLINE_PROBABILITY в config.php.

$inlineProbability = defined('QUEUE_INLINE_PROBABILITY') ? QUEUE_INLINE_PROBABILITY : 0;
if (php_sapi_name() !== 'cli' && mt_rand(1, 100) <= $inlineProbability) {
    try {
        $jobs = Queue::pop(1, null, [Queue::TYPE_MODERATION_CHECK, Queue::TYPE_MODERATION_REWRITE]); // Взяти лише 1 задачу, крім важких AI-задач (модерація та переписування)
        if (!empty($jobs)) {
            $job = $jobs[0];
            require_once __DIR__ . '/Moderation.php';
            require_once __DIR__ . '/mailer.php';

            $handlers = [
                Queue::TYPE_MODERATION_CHECK   => 'inlineModerationCheck',
                Queue::TYPE_MODERATION_REWRITE  => 'inlineModerationRewrite',
                Queue::TYPE_EMAIL_VERIFY       => 'inlineEmailVerify',
                Queue::TYPE_EMAIL_CHANGED     => 'inlineEmailChanged',
                Queue::TYPE_EMAIL_CUSTOM      => 'inlineEmailCustom',
            ];

            $type = $job['type'];
            if (isset($handlers[$type])) {
                try {
                    call_user_func($handlers[$type], $job['payload']);
                    Queue::complete($job['id']);
                } catch (\Throwable $e) {
                    Queue::fail($job['id'], get_class($e) . ': ' . $e->getMessage());
                }
            }
        }
    } catch (\Throwable $e) {
        // Лінива обробка не повинна ламати основний запит
        error_log('Inline worker помилка: ' . $e->getMessage());
    }
}

/**
 * Inline-обробники для лінивої черги.
 * Дублюють логіку з cron/worker.php, але без логування у файл.
 */
function inlineModerationCheck(array $payload): void {
    $pasteId = $payload['paste_id'] ?? null;
    $content = $payload['content'] ?? '';
    if (!$pasteId) return;

    $pdo = DB::getInstance()->getPDO();
    $stmt = $pdo->prepare("SELECT id, moderation_status FROM pastes WHERE id = ?");
    $stmt->execute([$pasteId]);
    $paste = $stmt->fetch();
    if (!$paste || $paste['moderation_status'] !== 'pending') return;

    $violations = Moderation::checkExternal($content);
    if ($violations === false) {
        $pdo->prepare("UPDATE pastes SET moderation_status = 'approved' WHERE id = ?")->execute([$pasteId]);
    } else {
        $pdo->prepare("UPDATE pastes SET moderation_status = 'rejected', moderation_result = ? WHERE id = ?")
            ->execute([json_encode($violations, JSON_UNESCAPED_UNICODE), $pasteId]);
    }
}

function inlineModerationRewrite(array $payload): void {
    $pasteId = $payload['paste_id'] ?? null;
    if (!$pasteId) return;

    $pdo = DB::getInstance()->getPDO();
    $stmt = $pdo->prepare("SELECT id, content FROM pastes WHERE id = ? AND is_pending_rewrite = 1");
    $stmt->execute([$pasteId]);
    $paste = $stmt->fetch();
    if (!$paste) return;

    $rewritten = Moderation::rewrite($paste['content']);
    $pdo->prepare("UPDATE pastes SET content = ?, is_pending_rewrite = 0, moderation_status = 'approved' WHERE id = ?")
        ->execute([$rewritten, $pasteId]);

    $pasteObj = Paste::findById($pasteId);
    if ($pasteObj) {
        $pasteObj->syncTags();
    }
}

function inlineEmailVerify(array $payload): void {
    $to   = $payload['to'] ?? '';
    $code = $payload['code'] ?? '';
    if (empty($to) || empty($code)) return;

    $template = file_get_contents(__DIR__ . '/../templates/email_verify.html');
    $html = str_replace('{{CODE}}', htmlspecialchars($code), $template);
    Mailer::sendDirect($to, 'Підтвердження пошти — PayPaste', $html);
}

function inlineEmailChanged(array $payload): void {
    $oldEmail = $payload['old_email'] ?? '';
    $newEmail = $payload['new_email'] ?? '';
    if (empty($oldEmail) || empty($newEmail)) return;

    $template = file_get_contents(__DIR__ . '/../templates/email_changed.html');
    $html = str_replace('{{NEW_EMAIL}}', htmlspecialchars($newEmail), $template);
    Mailer::sendDirect($oldEmail, 'Зміна email-адреси — PayPaste', $html);
}

function inlineEmailCustom(array $payload): void {
    $to      = $payload['to'] ?? '';
    $subject = $payload['subject'] ?? '';
    $html    = $payload['html'] ?? '';
    if (empty($to) || empty($subject)) return;

    Mailer::sendDirect($to, $subject, $html);
}
