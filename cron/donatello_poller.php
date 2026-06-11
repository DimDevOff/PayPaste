<?php
/**
 * Donatello Poller — активне опитування API Donatello для обробки донатів.
 *
 * Donatello НЕ підтримує вихідні вебхуки, тому ми періодично опитуємо
 * їхній REST API: GET https://donatello.to/api/v1/donates
 *
 * Запуск: php cron/donatello_poller.php
 * Або через systemd timer / cron кожні 60 секунд.
 */

require_once __DIR__ . '/../includes/repositories/Repo.php';
require_once __DIR__ . '/../includes/models/Order.php';
require_once __DIR__ . '/../includes/models/User.php';
require_once __DIR__ . '/../includes/services/CreditService.php';
require_once __DIR__ . '/../includes/services/PricingService.php';
require_once __DIR__ . '/../includes/HttpClient.php';

// ─── Конфігурація ──────────────────────────────────────────────
if (!defined('DONATELLO_TOKEN') || empty(DONATELLO_TOKEN)) {
    error_log('[DonatelloPoller] DONATELLO_TOKEN не налаштовано');
    exit(1);
}

define('DONATELLO_API_DONATES', 'https://donatello.to/api/v1/donates');
define('POLLER_LOCK_FILE', __DIR__ . '/../data/donatello_poller.lock');
define('POLLER_LAST_ID_FILE', __DIR__ . '/../data/donatello_last_pubid.txt');

// ─── Захист від паралельного запуску ───────────────────────────
$lock_fp = @fopen(POLLER_LOCK_FILE, 'w');
if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB)) {
    error_log('[DonatelloPoller] Попередній запуск ще виконується, вихід');
    exit(0);
}

// ─── Завантаження останнього обробленого pubId ──────────────────
$lastPubId = @file_get_contents(POLLER_LAST_ID_FILE);
$lastPubId = $lastPubId ? trim($lastPubId) : null;

// ─── Запит до API Donatello ────────────────────────────────────
$http = new HttpClient();
try {
    $result = $http->getJson(DONATELLO_API_DONATES, [
        'X-Donatello-Token: ' . DONATELLO_TOKEN
    ], 30);
    $data = json_decode($result['body'], true);
} catch (\Throwable $e) {
    error_log('[DonatelloPoller] Помилка запиту API: ' . $e->getMessage());
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
    exit(1);
}

if (empty($data['success']) || !isset($data['content'])) {
    error_log('[DonatelloPoller] Неочікувана відповідь API: ' . substr($result['body'], 0, 500));
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
    exit(1);
}

// ─── Обробка донатів ───────────────────────────────────────────
$donates = $data['content'];
$newLastPubId = $lastPubId;
$processedCount = 0;

// Donatello повертає донати від найновіших до найстаріших.
// Обробляємо тільки нові (яких ще не бачили).
foreach ($donates as $donate) {
    $pubId = $donate['pubId'] ?? ($donate['pub_id'] ?? '');

    // Зупиняємось, коли дійшли до вже обробленого
    if ($lastPubId && $pubId === $lastPubId) {
        break;
    }

    // Запам'ятовуємо перший (найновіший) pubId
    if ($newLastPubId === $lastPubId) {
        $newLastPubId = $pubId;
    }

    $message = $donate['message'] ?? '';
    $amount = (float)($donate['amount'] ?? 0);

    if (empty($message) || $amount <= 0) {
        continue;
    }

    // Пошук ID замовлення у повідомленні (формат: order_xxxxx)
    if (!preg_match('/order_[a-zA-Z0-9]+/', $message, $matches)) {
        continue;
    }
    $order_id = $matches[0];

    $order = Order::findById($order_id);
    if (!$order || $order->status !== 'pending') {
        continue;
    }

    // Розрахунок кредитів
    $credits = PricingService::creditsForDonatello($amount);

    $user = User::findById($order->user_id);
    if (!$user) {
        error_log("[DonatelloPoller] Користувача не знайдено для order {$order_id}");
        continue;
    }

    // Нарахування кредитів
    try {
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

        $order->status = 'completed';
        $order->amount_credits = $credits;
        $order->service = 'donatello';
        $order->external_provider_id = $pubId;
        $order->save();

        $processedCount++;
        error_log("[DonatelloPoller] ✅ {$credits} кредитів для user={$user->id}, order={$order_id}, amount={$amount} UAH");
    } catch (\Throwable $e) {
        error_log("[DonatelloPoller] ❌ Помилка нарахування: {$e->getMessage()}");
    }
}

// ─── Збереження останнього pubId ───────────────────────────────
if ($newLastPubId && $newLastPubId !== $lastPubId) {
    file_put_contents(POLLER_LAST_ID_FILE, $newLastPubId);
}

flock($lock_fp, LOCK_UN);
fclose($lock_fp);

if ($processedCount > 0) {
    error_log("[DonatelloPoller] Оброблено {$processedCount} донатів");
}
