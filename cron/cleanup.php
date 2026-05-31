<?php
/**
 * Скрипт для очищення протермінованих паст.
 * Запускається виключно через CLI / crontab.
 */

// Блокуємо прямий веб-доступ — cron-скрипти не повинні виконуватись через браузер
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Доступ заборонено. Цей скрипт призначений лише для CLI.');
}

// Worker не потребує сесій, CSRF, кукі — працює в CLI
define('NO_SESSION', true);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/services/PasteService.php';

echo "[" . date('Y-m-d H:i:s') . "] Початок очищення протермінованих паст...\n";

try {
    $deletedCount = PasteService::cleanupExpired();
    echo "[" . date('Y-m-d H:i:s') . "] Очищення завершено. Видалено паст: $deletedCount\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Помилка при очищенні: " . $e->getMessage() . "\n";
}

// Очищення старих завершених/мертвих задач з черги
try {
    $jobsDeleted = Queue::cleanup();
    echo "[" . date('Y-m-d H:i:s') . "] Очищення черги: видалено $jobsDeleted старих задач.\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Помилка при очищенні черги: " . $e->getMessage() . "\n";
}
