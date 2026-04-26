<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/controllers/PasteController.php';

$controller = new PasteController();
$controller->handleRequest();

// Завантаження всіх View і головного шаблону домашньої сторінки
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/home.php';
require_once __DIR__ . '/templates/footer.php';
