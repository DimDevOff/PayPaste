<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/controllers/PasteController.php';

$controller = new PasteController();
$controller->handleRequest();

// ── Підготовка даних для шаблону (винесено з templates/create.php) ──
$maxCredits = isset($_SESSION['user_id']) ? (int)(User::findById($_SESSION['user_id'])->credits ?? 0) : 0;

// Завантаження всіх View і головного шаблону створення пасти
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/create.php';
require_once __DIR__ . '/templates/footer.php';