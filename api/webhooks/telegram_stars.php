<?php
require_once __DIR__ . '/../../includes/models/Order.php';
require_once __DIR__ . '/../../includes/models/User.php';
require_once __DIR__ . '/../../includes/services/CreditService.php';
require_once __DIR__ . '/../../includes/services/PricingService.php';

// Верифікація webhook-запиту: перевіряємо secret_token від Telegram
$secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!defined('TELEGRAM_WEBHOOK_SECRET') || empty(TELEGRAM_WEBHOOK_SECRET) || !hash_equals(TELEGRAM_WEBHOOK_SECRET, $secret)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'несанкціонований доступ']);
    exit;
}

// Токен бота. Обов'язково повинен бути налаштований у .env
if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'TELEGRAM_BOT_TOKEN не налаштований']);
    exit;
}
$bot_token = TELEGRAM_BOT_TOKEN;

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    echo "Bot is running.";
    exit;
}

// Функція для відправки запитів до Telegram API
function tgRequest($method, $data = []) {
    global $bot_token;
    $url = "https://api.telegram.org/bot$bot_token/$method";
    $http = new \HttpClient();
    $result = $http->postJson($url, $data);
    return json_decode($result['body'], true);
}

// 1. Обробка стартового повідомлення (/start order_xxx)
if (isset($update['message']['text'])) {
    $text = $update['message']['text'];
    $chat_id = $update['message']['chat']['id'];

    if (strpos($text, '/start ') === 0) {
        $order_id = str_replace('/start ', '', $text);
        
        // Відправляємо повідомлення з Inline кнопками для вибору тарифу
        $keyboard = PricingService::getStarsInlineKeyboard($order_id);

        tgRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "Оберіть тарифний план для поповнення балансу.\nКод замовлення: {$order_id}",
            'reply_markup' => $keyboard
        ]);
    }
}

// 2. Обробка натискання на Inline кнопку (callback_query) => відправка Invoice
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data']; // Напр: tariff_50_order_abc123

    if (preg_match('/^tariff_([0-9]+)_(order_.+)$/', $data, $matches)) {
        $stars_amount = (int)$matches[1];
        $order_id = $matches[2];

        $tariff = PricingService::tariffForStars($stars_amount);
        $title = $tariff ? $tariff['label'] . ' пакет' : "Рандомний тариф";
        $description = $tariff ? $tariff['description'] : "Поповнення кредитів";

        tgRequest('sendInvoice', [
            'chat_id' => $chat_id,
            'title' => $title,
            'description' => $description,
            'payload' => $order_id . '|' . $stars_amount, // Передаємо у payload order_id та кількість зірок
            'currency' => 'XTR',
            'prices' => [
                ['label' => 'Total', 'amount' => $stars_amount]
            ]
        ]);
        
        tgRequest('answerCallbackQuery', [
            'callback_query_id' => $callback['id']
        ]);
    }
}

// 3. Обробка pre_checkout_query (Обов'язковий запит перед оплатою)
if (isset($update['pre_checkout_query'])) {
    $pre_checkout_query_id = $update['pre_checkout_query']['id'];
    
    tgRequest('answerPreCheckoutQuery', [
        'pre_checkout_query_id' => $pre_checkout_query_id,
        'ok' => true
    ]);
}

// 4. Обробка успішної оплати (successful_payment)
if (isset($update['message']['successful_payment'])) {
    $payment = $update['message']['successful_payment'];
    $chat_id = $update['message']['chat']['id'];
    $payload = $payment['invoice_payload']; // "order_abc123|50"
    
    list($order_id, $stars) = explode('|', $payload);
    $stars = (int)$stars;

    $credits = PricingService::creditsForStars((int)$stars);

    $order = Order::findById($order_id);
    if ($order && $order->status === 'pending') {
        $user = User::findById($order->user_id);
        if ($user) {
            CreditService::credit(
                $user,
                $credits,
                'topup',
                "Поповнення через Telegram Stars ($stars ⭐)",
                null,
                $order->id,
                'tg_stars',
                'topup:tg_stars:' . $order->id
            );

            $order->status = 'completed';
            $order->amount_credits = $credits;
            $order->service = 'tg_stars';
            $order->external_provider_id = $payment['telegram_payment_charge_id'] ?? bin2hex(random_bytes(16));
            $order->save();

            tgRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ Оплата успішна! Вам нараховано $credits кредитів.\nВи можете повернутися на сайт."
            ]);
            exit;
        }
    }
    
    // Якщо замовлення не знайдено або вже виконано
    tgRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "⚠️ Оплата пройшла, але виникла помилка із замовленням $order_id. Зверніться до підтримки."
    ]);
}

// Повертаємо 200 OK для Telegram, щоб не слав повторно
http_response_code(200);
