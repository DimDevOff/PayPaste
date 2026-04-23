<?php
/**
 * Webhook для підтвердження перегляду реклами (Adsterra Quest)
 * Збільшує лічильник у сесії.
 */
session_start();

header('Content-Type: application/json');

// Перевірка CSRF
require_once __DIR__ . '/../../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Ініціалізація лічильника, якщо його немає
if (!isset($_SESSION['ads_watched'])) {
    $_SESSION['ads_watched'] = 0;
}

// Збільшуємо лічильник
$_SESSION['ads_watched']++;

$remaining = 3 - $_SESSION['ads_watched'];
$done = $_SESSION['ads_watched'] >= 3;

echo json_encode([
    'success' => true,
    'ads_watched' => $_SESSION['ads_watched'],
    'remaining' => max(0, $remaining),
    'done' => $done
]);
