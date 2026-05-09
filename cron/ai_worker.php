<?php
/**
 * Сумісний wrapper — делегує роботу новому уніфікованому worker-у
 * з фільтром за типом moderation_rewrite (тільки AI-переписування).
 *
 * Рекомендується використовувати: php cron/worker.php
 */
// Додаємо --type=moderation_rewrite до аргументів командного рядка
$argv[] = '--type=moderation_rewrite';
$argc = count($argv);
require_once __DIR__ . '/worker.php';
