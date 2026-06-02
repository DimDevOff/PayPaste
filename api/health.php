<?php
/**
 * Health check endpoint для моніторингу.
 * 
 * Використання:
 *   curl https://paypaste.dimdevoff.co.ua/api/health
 *   curl https://paypaste.dimdevoff.co.ua/api/health?full=1
 *
 * Uptime monitoring (UptimeRobot, BetterUptime):
 *   https://paypaste.dimdevoff.co.ua/api/health
 *
 * Docker healthcheck:
 *   HEALTHCHECK --interval=30s --timeout=5s CMD curl -f http://localhost/api/health
 */

// ─── Спрощена ініціалізація без сесій, CSRF, шаблонів ────────────────────
define('NO_SESSION', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$startTime = microtime(true);

$checks = [
    'status' => 'ok',
    'timestamp' => time(),
    'php_version' => PHP_VERSION,
];

// ─── 1. База даних ────────────────────────────────────────────────────────
try {
    $pdo = DB::getInstance()->getPDO();
    $stmt = $pdo->query('SELECT 1 AS alive');
    $result = $stmt->fetch();
    $checks['database'] = $result && $result['alive'] == '1' ? 'ok' : 'error';
} catch (\Throwable $e) {
    $checks['database'] = 'error';
    $checks['database_error'] = $e->getMessage();
    $checks['status'] = 'degraded';
}

// ─── 2. Статистика (опціонально, тільки з ?full=1) ───────────────────────
$showFull = isset($_GET['full']);
if ($showFull) {
    $checks['full'] = true;

    // Кількість паст
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM pastes');
        $checks['pastes_count'] = (int)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        $checks['pastes_count'] = -1;
    }

    // Кількість користувачів
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM users');
        $checks['users_count'] = (int)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        $checks['users_count'] = -1;
    }

    // Черга — скільки задач очікують
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE status = 'pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW())");
        $stmt->execute();
        $checks['queue_pending'] = (int)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        $checks['queue_pending'] = -1;
    }

    // Останній heartbeat worker-а (якщо колись реалізуємо)
    $workerLog = __DIR__ . '/../data/logs/worker.log';
    if (file_exists($workerLog)) {
        $checks['worker_last_active'] = date('Y-m-d H:i:s', filemtime($workerLog));
    } else {
        $checks['worker_last_active'] = 'never';
    }

    // Диск
    $dataPath = __DIR__ . '/../data';
    if (is_dir($dataPath)) {
        $checks['disk_free_mb'] = round(disk_free_space($dataPath) / 1024 / 1024);
        $checks['disk_total_mb'] = round(disk_total_space($dataPath) / 1024 / 1024);
    }
}

// ─── 3. Час відповіді ─────────────────────────────────────────────────────
$checks['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

// ─── 4. HTTP статус ───────────────────────────────────────────────────────
$httpCode = $checks['status'] === 'ok' ? 200 : 503;
http_response_code($httpCode);

echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
