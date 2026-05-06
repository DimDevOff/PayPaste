<?php
header('Content-Type: application/json');
require_once __DIR__ . '/api_helper.php';
require_once __DIR__ . '/../includes/models/Paste.php';
require_once __DIR__ . '/../includes/models/User.php';
require_once __DIR__ . '/../includes/services/PasteService.php';

$user = authenticate_api();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// Якщо використовується PATH_INFO (наприклад, /api/pastes/p_123)
if (!$id && !empty($_SERVER['PATH_INFO'])) {
    $id = trim($_SERVER['PATH_INFO'], '/');
}

switch ($method) {
    case 'GET':
        handleGet($id, $user);
        break;
    case 'POST':
        handlePost($user);
        break;
    case 'DELETE':
        handleDelete($id, $user);
        break;
    default:
        json_response(['error' => 'Метод не дозволено.'], 405);
}

/**
 * Отримання пасти
 */
function handleGet($id, $user) {
    if (!$id) json_response(['error' => 'ID пасти обов\'язковий.'], 400);
    
    $paste = Paste::findById($id);
    if (!$paste) json_response(['error' => 'Пасту не знайдено або термін дії закінчився.'], 404);
    
    // Перевірка приватності
    if ($paste->is_private && $paste->user_id !== $user->id && $user->role !== 'admin') {
        json_response(['error' => 'Це приватна паста. Доступ заборонено.'], 403);
    }
    
    // Автоматична оплата при GET, якщо паста платна та заблокована
    if (PasteService::isLocked($paste, $user)) {
        $result = PasteService::unlock($paste->id, $user->id);
        if (!$result['success']) {
            json_response([
                'error' => $result['message'] ?: 'Паста платна. У вас недостатньо кредитів.',
                'cost' => $paste->view_cost,
                'balance' => $user->credits
            ], 402); // Payment Required
        }
    }
    
    json_response([
        'id' => $paste->id,
        'title' => $paste->title,
        'content' => $paste->content,
        'author' => $paste->user_id,
        'created_at' => $paste->created_at,
        'expires_at' => $paste->expires_at,
        'is_paid' => $paste->is_paid,
        'view_cost' => $paste->view_cost,
        'language' => $paste->language,
        'tags' => $paste->getTags()
    ]);
}

/**
 * Створення пасти
 */
function handlePost($user) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    // Мапінг API-полів у формат PasteService::create()
    $data = [
        'title' => $input['title'] ?? 'Без назви (API)',
        'content' => $input['content'] ?? '',
        'is_private' => (isset($input['is_private']) && $input['is_private']) ? '1' : null,
        'is_paid' => (isset($input['is_paid']) && $input['is_paid']) ? '1' : null,
        'view_cost' => $input['view_cost'] ?? 0,
        'expires_in' => isset($input['ttl_minutes']) ? (int)$input['ttl_minutes'] : 10080, // 7 днів за замовчуванням
        'language' => $input['language'] ?? 'plaintext',
        'tags' => $input['tags'] ?? '',
    ];
    
    try {
        $paste = PasteService::create($data, $user->id);
        
        json_response([
            'id' => $paste->id,
            'url' => APP_URL . "/view.php?id=" . $paste->id,
            'title' => $paste->title,
            'creation_fee' => CreditService::calculateCreationCost($paste->content),
            'remaining_credits' => $user->credits
        ], 201);
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Недостатньо кредитів') !== false || strpos($msg, 'creation_fee') !== false) {
            json_response([
                'error' => $msg,
                'required' => CreditService::calculateCreationCost($data['content'] ?? ''),
                'balance' => $user->credits
            ], 402);
        }
        json_response(['error' => $msg], 400);
    }
}

/**
 * Видалення пасти
 */
function handleDelete($id, $user) {
    if (!$id) json_response(['error' => 'ID пасти обов\'язковий.'], 400);
    
    try {
        PasteService::delete($id, $user->id);
        json_response(['success' => true, 'message' => 'Пасту видалено.']);
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'не знайдено') !== false) {
            json_response(['error' => 'Пасту не знайдено.'], 404);
        }
        json_response(['error' => $msg], 403);
    }
}
