<?php
session_start();
// Завантаження моделей
require_once __DIR__ . '/includes/models/User.php';

// Перевірка авторизації
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Увійдіть, щоб поповнити баланс!";
    header("Location: login.php");
    exit;
}

$user = User::findById($_SESSION['user_id']);

// Генерація ID замовлення
$order_id = 'order_' . substr(md5($user->id . time()), 0, 8);

require_once __DIR__ . '/includes/models/Order.php';
$order = new Order([ // Створення нового замовлення
    'id' => $order_id,
    'user_id' => $user->id,
    'service' => 'unknown',
    'amount_credits' => 0,
    'status' => 'pending'
]);
$order->save();

// Завантаження всіх View і головного шаблону сторінки поповнення балансу
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/credits.php';
require_once __DIR__ . '/templates/footer.php';
