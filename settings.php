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

// ── Підготовка даних для шаблону (винесено з templates/settings.php) ──
$user = User::findById($_SESSION['user_id']);
if (!$user) {
    echo "Користувача не знайдено.";
    exit;
}
$myPastes = Paste::findByUserId($_SESSION['user_id']);
$myPasskeys = Passkey::findByUserId($_SESSION['user_id']);

// Завантаження всіх View і головного шаблону налаштувань
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/settings.php';
require_once __DIR__ . '/templates/footer.php';
