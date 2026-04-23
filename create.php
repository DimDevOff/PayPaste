<?php
session_start();
// Завантаження контролера паст
require_once __DIR__ . '/includes/controllers/PasteController.php';

$controller = new PasteController();
$controller->handleRequest();

// Завантаження всіх View і головного шаблону створення пасти
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/create.php';
require_once __DIR__ . '/templates/footer.php';