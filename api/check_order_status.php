<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/models/Order.php';
require_once __DIR__ . '/../includes/models/User.php';

header('Content-Type: application/json');

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    echo json_encode(['error' => 'Відсутній order_id']);
    exit;
}

$order = Order::findById($order_id);

if (!$order) {
    echo json_encode(['exists' => false]);
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