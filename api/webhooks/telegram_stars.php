<?php
require_once __DIR__ . '/../../includes/models/Order.php';
require_once __DIR__ . '/../../includes/models/User.php';
require_once __DIR__ . '/../../includes/models/Transaction.php';

// Токен бота. Отримуємо з .env файлу або залишаємо дефолт
$bot_token = TELEGRAM_BOT_TOKEN ?: 'YOUR_BOT_TOKEN_HERE'; 

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
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 1. Обробка стартового повідомлення (/start order_xxx)
if (isset($update['message']['text'])) {
    $text = $update['message']['text'];
    $chat_id = $update['message']['chat']['id'];

    if (strpos($text, '/start ') === 0) {
        $order_id = str_replace('/start ', '', $text);
        
        // Відправляємо повідомлення з Inline кнопками для вибору тарифу
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🥉 Базовий - 50 ⭐ (100 кре.)', 'callback_data' => 'tariff_50_' . $order_id]
                ],
                [
                    ['text' => '🥈 Стандартний - 200 ⭐ (500 кре.)', 'callback_data' => 'tariff_200_' . $order_id]
                ],
                [
                    ['text' => '🥇 Преміум - 500 ⭐ (1500 кре.)', 'callback_data' => 'tariff_500_' . $order_id]
                ]
            ]
        ];

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

        $title = "Рандомний тариф";
        $description = "Поповнення кредитів";
        if ($stars_amount == 50) {
            $title = "Базовий пакет";
            $description = "100 кредитів на ваш аккаунт Trashy Pastebin";
        } elseif ($stars_amount == 200) {
            $title = "Стандартний пакет";
            $description = "500 кредитів на ваш аккаунт Trashy Pastebin";
        } elseif ($stars_amount == 500) {
            $title = "Преміум пакет";
            $description = "1500 кредитів на ваш аккаунт Trashy Pastebin";
        }

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

    $credits = 0;
    if ($stars == 50) {
        $credits = 100;
    } elseif ($stars == 200) {
        $credits = 500;
    } elseif ($stars >= 500) {
        $credits = 1500;
    }

    $order = Order::findById($order_id);
    if ($order && $order->status === 'pending') {
        $user = User::findById($order->user_id);
        if ($user) {
            $user->credits += $credits;
            $user->save();

            $order->status = 'completed';
            $order->amount_credits = $credits;
            $order->service = 'tg_stars';
            $order->external_provider_id = $payment['telegram_payment_charge_id'] ?? 'tg_'.time();
            $order->save();

            // Запис транзакції
            $tx = new Transaction([
                'user_id' => $user->id,
                'amount' => $credits,
                'type' => 'topup',
                'service' => 'tg_stars',
                'related_order_id' => $order->id,
                'description' => "Поповнення через Telegram Stars ($stars ⭐)"
            ]);
            $tx->save();

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

