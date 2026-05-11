<?php
require_once __DIR__ . '/api_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Метод не дозволено. Використовуйте POST.'], 405);
}

// Отримання даних з JSON або POST
$input = json_decode(file_get_contents('php://input'), true);
$api_key = $input['api_key'] ?? $_POST['api_key'] ?? '';

// Rate Limiting для отримання токена за ключем, сесією/браузером і м'яким IP-фактором.
if (!RateLimiter::checkAction('api_auth', 5, 60, ['subject' => $api_key ?: 'missing_api_key', 'ip_limit' => 150])) {
    json_response(['error' => 'Занадто багато спроб авторизації. Спробуйте через хвилину.'], 429);
}

if (empty($api_key)) {
    json_response(['error' => 'API ключ обов\'язковий.'], 400);
}

$user = User::findByApiKey($api_key);

if (!$user) {
    json_response(['error' => 'Невірний API ключ.'], 401);
}

// Створення JWT
$payload = [
    'sub' => $user->id,
    'nickname' => $user->nickname,
    'iat' => time(),
    'exp' => time() + (3600 * 24 * 7) // Токен на 7 днів
];

$token = JWT::encode($payload);

json_response([
    'access_token' => $token,
    'token_type' => 'Bearer',
    'expires_in' => 3600 * 24 * 7
]);
