<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/jwt.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/models/Transaction.php';

/**
 * Функція автентифікації для REST API.
 * Перевіряє JWT токен і знімає плату за запит (1 кредит).
 */
function authenticate_api() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!str_starts_with($authHeader, 'Bearer ')) {
        json_response(['error' => 'Відсутній або некоректний Authorization заголовок (очікується Bearer <token>)'], 401);
    }
    
    $token = substr($authHeader, 7);
    $payload = JWT::decode($token);
    
    if (!$payload || !isset($payload['sub'])) {
        json_response(['error' => 'Недійсний або протермінований токен.'], 401);
    }
    
    $user = User::findById($payload['sub']);
    if (!$user) {
        json_response(['error' => 'Користувача не знайдено.'], 401);
    }
    
    // Rate Limiting для API (за ключем JWT / IP)
    if (!RateLimiter::check('api:' . $user->id, 60, 60)) { // 60 запитів на хвилину
        json_response(['error' => 'Занадто багато запитів (Rate Limit).'], 429);
    }
    
    // ПЛАТНИЙ ЗАПИТ: За ідеєю сервісу, кожен запит знімає 1 кредит
    $api_fee = 1;
    if ($user->credits < $api_fee) {
        json_response([
            'error' => 'Недостатньо кредитів для виконання API запиту.', 
            'required' => $api_fee, 
            'balance' => $user->credits
        ], 402);
    }
    
    // Списуємо кредит та записуємо транзакцію
    $user->credits -= $api_fee;
    $user->save();
    
    Transaction::create($user->id, -$api_fee, 'api_usage', null, null, null, "Плата за API запит");
    
    return $user;
}

/**
 * Хелпер для JSON відповіді.
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
