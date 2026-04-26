<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/controllers/AuthController.php';

$controller = new AuthController();
$controller->handleRequest();

// Завантаження всіх View і головного шаблону сторінки авторизації
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/login.php';
require_once __DIR__ . '/templates/footer.php';
