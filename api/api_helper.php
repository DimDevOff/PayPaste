<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/jwt.php';

/**
 * Перевіряє JWT токен у заголовках запиту.
 * Повертає об'єкт User або зупиняє виконання з помилкою 401.
 */
function authenticate_api() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'Відсутній або невірний заголовок Authorization. Очікується Bearer <token>.']);
        exit;
    }
    
    $token = substr($authHeader, 7);
    $payload = JWT::decode($token);
    
    if (!$payload || !isset($payload['sub'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Невірний або протермінований токен.']);
        exit;
    }
    
    $user = User::findById($payload['sub']);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Користувача не знайдено.']);
        exit;
    }
    
    return $user;
}

/**
 * Хелпер для відправки JSON відповідей
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
