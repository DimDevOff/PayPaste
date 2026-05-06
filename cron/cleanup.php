<?php
/**
 * Скрипт для очищення протермінованих паст.
 * Рекомендується запускати через Cron (наприклад, кожну годину).
 */

// Перевірка, чи запущено через CLI (рекомендовано для крона)
if (php_sapi_name() !== 'cli' && !isset($_GET['force'])) {
    die("Цей скрипт призначений для запуску через CLI або з параметром ?force=1\n");
}

require_once __DIR__ . '/../includes/services/PasteService.php';

echo "[" . date('Y-m-d H:i:s') . "] Початок очищення протермінованих паст...\n";

try {
    $deletedCount = PasteService::cleanupExpired();
    echo "[" . date('Y-m-d H:i:s') . "] Очищення завершено. Видалено паст: $deletedCount\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Помилка при очищенні: " . $e->getMessage() . "\n";
}
