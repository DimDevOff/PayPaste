<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/']);
    session_start();
}
require_once __DIR__ . '/../includes/repositories/Repo.php';
require_once __DIR__ . '/../includes/models/Order.php';
require_once __DIR__ . '/../includes/models/User.php';

header('Content-Type: application/json; charset=utf-8');

// Перевірка авторизації користувача
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Неавторизований доступ. Будь ласка, увійдіть в акаунт.']);
    exit;
}

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Відсутній order_id']);
    exit;
}

$order = Order::findById($order_id);

if (!$order) {
    echo json_encode(['exists' => false]);
    exit;
}

// Перевірка прав доступу: замовлення має належати поточному користувачу або користувач має бути адміністратором
if ($order->user_id !== $_SESSION['user_id'] && ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ заборонено. Ви не є власником цього замовлення.']);
    exit;
}

$user = User::findById($order->user_id);

// Якщо замовлення виконано, перезавантажимо користувача для актуального балансу
if ($order->status === 'completed' && $user) {
    $user = User::findById($order->user_id);
    session_write_close(); // Примусово зберігаємо сесію прямо зараз
}

echo json_encode([
    'exists' => true,
    'status' => $order->status,
    'amount_credits' => $order->amount_credits ?? 0,
    'current_balance' => $user->credits ?? 0,
    'service' => $order->service
]);