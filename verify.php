<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/controllers/VerifyController.php';

$controller = new VerifyController();
$controller->handleRequest();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/verify.php';
require_once __DIR__ . '/templates/footer.php';
