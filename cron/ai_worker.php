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
// Очищуємо аргументи командного рядка, щоб забезпечити детерміністичну поведінку.
// Завжди встановлюємо тип задачі як moderation_rewrite та ігноруємо інші типи.
$newArgv = [__DIR__ . '/worker.php', '--type=moderation_rewrite'];

// Зберігаємо прапорець --daemon, якщо він був переданий
if (in_array('--daemon', $argv ?? [])) {
    $newArgv[] = '--daemon';
}

$argv = $newArgv;
$argc = count($argv);
require_once __DIR__ . '/worker.php';
