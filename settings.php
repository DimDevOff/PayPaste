<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!getCurrentUser()) { // Перевірка авторизації
    header("Location: login.php");
    exit;
}
// Завантаження контролера налаштувань
require_once __DIR__ . '/includes/controllers/SettingsController.php';

$controller = new SettingsController();
$controller->handleRequest();

// Завантаження всіх View і головного шаблону налаштувань
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/settings.php';
require_once __DIR__ . '/templates/footer.php';
