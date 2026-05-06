<?php
/**
 * Вебхук для обробки сповіщень від платіжної платформи Donatello.
 * Цей файл приймає POST-запити від Donatello, перевіряє їх на справжність
 * та нараховує кредити користувачам на основі суми донату та ID замовлення в коментарі.
 */
require_once __DIR__ . '/../../includes/models/Order.php';
require_once __DIR__ . '/../../includes/models/User.php';
require_once __DIR__ . '/../../includes/services/CreditService.php';

// Очікуваний токен для верифікації запитів від Donatello
$expected_token = DONATELLO_TOKEN ?: 'PASTE_YOUR_TOKEN';
$headers = getallheaders();
$header_token = $headers['X-Donatello-Token'] ?? $headers['X-Token'] ?? $headers['X-Key'] ?? '';

if ($header_token !== $expected_token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Не авторизовано']);
    exit;
}

// Отримання сирих даних запиту
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Donatello може відправляти дані як JSON або як POST-форми, тому тре це провірити.
if (!$data && isset($_POST['message'])) {
    $data = $_POST;
}

if (!empty($data)) {
    // Обробка одиничного донату
    if (isset($data['message']) && isset($data['amount'])) {
        process_donate($data);
    } 
    // Обробка масиву донатів (може бути при синхронізації через API)
    elseif (isset($data['content']) && is_array($data['content'])) {
        foreach ($data['content'] as $donate) {
            process_donate($donate);
        }
    }
}

echo json_encode(['success' => true]);

/**
 * Обробляє окремий донат: парсить коментар, знаходить замовлення та оновлює баланс користувача.
 *
 * @param array $donate Дані про донат від Donatello
 */
function process_donate($donate) {
    global $expected_token;
    $message = $donate['message'] ?? '';
    $amount = (float)($donate['amount'] ?? 0);
    $pubId = $donate['pubId'] ?? uniqid('don_');

    // Пошук ID замовлення у повідомленні донату (формат: order_xxxxx)
    if (preg_match('/order_[a-zA-Z0-9]+/', $message, $matches)) {
        $order_id = $matches[0];

        $order = Order::findById($order_id);
        if ($order && $order->status === 'pending') {
            
            // Розрахунок кількості кредитів на основі суми в UAH
            // Тарифна сітка:
            // 25 UAH  = 100 кредитів
            // 100 UAH = 500 кредитів
            // 250 UAH = 1500 кредитів
            $credits = 0;
            if ($amount >= 250) {
                $credits = 1500;
            } elseif ($amount >= 100) {
                $credits = 500;
            } elseif ($amount >= 25) {
                $credits = 100;
            } else {
                // Формула для довільних сум (4 кредити за 1 UAH)
                $credits = floor($amount * 4); 
            }

            // Нарахування кредитів користувачу через CreditService
            $user = User::findById($order->user_id);
            if ($user) {
                CreditService::credit(
                    $user,
                    $credits,
                    'topup',
                    'Поповнення через Donatello на суму ' . $amount . ' UAH',
                    null,
                    $order->id,
                    'donatello'
                );

                // Оновлення статусу замовлення на 'completed'
                $order->status = 'completed';
                $order->amount_credits = $credits;
                $order->service = 'donatello';
                $order->external_provider_id = $pubId;
                $order->save();
            }
        }
    }
}

