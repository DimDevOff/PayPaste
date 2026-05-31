<?php
require_once __DIR__ . '/includes/bootstrap.php';

$user = getCurrentUser();
if (!$user) {
    $_SESSION['error'] = "Увійдіть, щоб поповнити баланс!";
    header("Location: login.php");
    exit;
}

// Генерація ID замовлення та створення замовлення лише при натисканні кнопки оплати
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = 'order_' . bin2hex(random_bytes(8));

    require_once __DIR__ . '/includes/models/Order.php';
    $order = new Order([
        'id' => $order_id,
        'user_id' => $user->id,
        'service' => 'unknown',
        'amount_credits' => 0,
        'status' => 'pending'
    ]);
    $order->save();
}

// Завантаження всіх View і головного шаблону сторінки поповнення балансу
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/credits.php';
require_once __DIR__ . '/templates/footer.php';
