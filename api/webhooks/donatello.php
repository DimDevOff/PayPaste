<?php
/**
 * Вебхук для обробки сповіщень від платіжної платформи Donatello.
 * Цей файл приймає POST-запити від Donatello, перевіряє їх на справжність
 * та нараховує кредити користувачам на основі суми донату та ID замовлення в коментарі.
 */
require_once __DIR__ . '/../../includes/repositories/Repo.php';
require_once __DIR__ . '/../../includes/models/Order.php';
require_once __DIR__ . '/../../includes/models/User.php';
require_once __DIR__ . '/../../includes/services/CreditService.php';
require_once __DIR__ . '/../../includes/services/PricingService.php';
require_once __DIR__ . '/../../includes/RateLimiter.php';

// IP-based rate limiting: не більше 30 запитів на хвилину з однієї IP
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!RateLimiter::check('webhook:donatello:' . $client_ip, 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Забагато запитів. Спробуйте пізніше.']);
    exit;
}

// Очікуваний токен для верифікації запитів від Donatello
if (!defined('DONATELLO_TOKEN') || empty(DONATELLO_TOKEN)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Сервер не налаштовано: відсутній DONATELLO_TOKEN']);
    exit;
}

// Отримання сирих даних запиту (читаємо ОДИН раз — php://input не можна читати повторно)
$raw_body = file_get_contents('php://input');

// Верифікація заголовка X-Donatello-Token (через $_SERVER — nginx-сумісно)
$header_token = $_SERVER['HTTP_X_DONATELLO_TOKEN'] ?? '';

if (!hash_equals(DONATELLO_TOKEN, $header_token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Невірний токен авторизації']);
    exit;
}

// HMAC-SHA256 верифікація підпису тіла запиту
$header_signature = $_SERVER['HTTP_X_DONATELLO_SIGNATURE'] ?? '';
$expected_signature = hash_hmac('sha256', $raw_body, DONATELLO_TOKEN);

if (!hash_equals($expected_signature, $header_signature)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Невірний підпис запиту']);
    exit;
}

// Парсинг JSON з раніше збереженого сирого тіла
$data = json_decode($raw_body, true);

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
    $message = $donate['message'] ?? '';
    $amount = (float)($donate['amount'] ?? 0);
    $pubId = $donate['pubId'] ?? uniqid('don_');

    // Пошук ID замовлення у повідомленні донату (формат: order_xxxxx)
    if (preg_match('/order_[a-zA-Z0-9]+/', $message, $matches)) {
        $order_id = $matches[0];

        $order = Order::findById($order_id);
        if ($order && $order->status === 'pending') {
            
            // Розрахунок кількості кредитів через PricingService
            $credits = PricingService::creditsForDonatello($amount);

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
                    'donatello',
                    'topup:donatello:' . $order->id
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

