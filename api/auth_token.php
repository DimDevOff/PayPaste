<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не дозволено. Використовуйте POST.']);
    exit;
}

// Отримання даних з JSON або POST
$input = json_decode(file_get_contents('php://input'), true);
$api_key = $input['api_key'] ?? $_POST['api_key'] ?? '';

if (empty($api_key)) {
    http_response_code(400);
    echo json_encode(['error' => 'API ключ обов\'язковий.']);
    exit;
}

$user = User::findByApiKey($api_key);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Невірний API ключ.']);
    exit;
}

// Створення JWT
$payload = [
    'sub' => $user->id,
    'nickname' => $user->nickname,
    'iat' => time(),
    'exp' => time() + (3600 * 24 * 7) // Токен на 7 днів
];

$token = JWT::encode($payload);

echo json_encode([
    'access_token' => $token,
    'token_type' => 'Bearer',
    'expires_in' => 3600 * 24 * 7
]);
