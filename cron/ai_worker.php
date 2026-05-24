<?php
/**
 * Сумісний wrapper — делегує роботу новому уніфікованому worker-у
 * з фільтром за типом moderation_rewrite (тільки AI-переписування).
 *
 * Запускається виключно через CLI / crontab.
 * Рекомендується використовувати: php cron/ai_worker.php
 */

// Блокуємо прямий веб-доступ — cron-скрипти не повинні виконуватись через браузер
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Доступ заборонено. Цей скрипт призначений лише для CLI.');
}
// Додаємо --type=moderation_rewrite до аргументів командного рядка
$argv[] = '--type=moderation_rewrite';
$argc = count($argv);
require_once __DIR__ . '/worker.php';
