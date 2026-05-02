<?php
header('Content-Type: application/json');
require_once __DIR__ . '/api_helper.php';
require_once __DIR__ . '/../includes/models/Paste.php';
require_once __DIR__ . '/../includes/models/Transaction.php';

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
    
    // Перевірка оплати
    if ($paste->is_paid && $paste->user_id !== $user->id && $user->role !== 'admin' && !$user->hasUnlocked($paste->id)) {
        // Спроба автоматичної оплати кредитами
        if ($user->credits < $paste->view_cost) {
            json_response([
                'error' => 'Паста платна. У вас недостатньо кредитів.',
                'cost' => $paste->view_cost,
                'balance' => $user->credits
            ], 402); // Payment Required
        }
        
        // Знімаємо кредити
        try {
            $pdo = DB::getInstance()->getPDO();
            $pdo->beginTransaction();
            
            $user->credits -= $paste->view_cost;
            $user->unlockPaste($paste->id); // Це викличе save() всередині
            
            // Запис транзакції
            Transaction::create($user->id, -$paste->view_cost, 'purchase', null, $paste->id, null, "Купівля доступу до пасти (API)");
            
            // Нараховуємо автору (якщо є)
            if ($paste->user_id) {
                $author = User::findById($paste->user_id);
                if ($author) {
                    $author->credits += $paste->view_cost;
                    $author->save();
                    Transaction::create($author->id, $paste->view_cost, 'sale', null, $paste->id, null, "Продаж доступу до пасти (API)");
                }
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_response(['error' => 'Помилка при обробці оплати.'], 500);
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
        'view_cost' => $paste->view_cost
    ]);
}

/**
 * Створення пасти
 */
function handlePost($user) {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $title = $input['title'] ?? 'Без назви (API)';
    $content = $input['content'] ?? '';
    $is_paid = isset($input['is_paid']) ? (bool)$input['is_paid'] : false;
    $view_cost = isset($input['view_cost']) ? (int)$input['view_cost'] : 0;
    $is_private = isset($input['is_private']) ? (bool)$input['is_private'] : false;
    $ttl_minutes = isset($input['ttl_minutes']) ? (int)$input['ttl_minutes'] : 10080; // 7 днів за замовчуванням
    
    if (empty($content)) {
        json_response(['error' => 'Контент пасти не може бути порожнім.'], 400);
    }
    
    // Розрахунок вартості створення
    $creation_fee = ceil(mb_strlen($content) / 10);
    
    if ($user->credits < $creation_fee) {
        json_response([
            'error' => 'Недостатньо кредитів для створення пасти.',
            'required' => $creation_fee,
            'balance' => $user->credits
        ], 402);
    }
    
    $expires_at = date('Y-m-d H:i:s', time() + ($ttl_minutes * 60));
    
    $paste = new Paste($title, $content, $user->id, $is_paid, $view_cost, $is_private, null, null, $expires_at);
    
    try {
        $pdo = DB::getInstance()->getPDO();
        $pdo->beginTransaction();
        
        $paste->save();
        
        $user->credits -= $creation_fee;
        $user->save();
        
        Transaction::create($user->id, -$creation_fee, 'creation_fee', null, $paste->id, null, "Комісія за створення пасти (API)");
        
        $pdo->commit();
        
        json_response([
            'id' => $paste->id,
            'url' => APP_URL . "/view.php?id=" . $paste->id,
            'title' => $paste->title,
            'creation_fee' => $creation_fee,
            'remaining_credits' => $user->credits
        ], 201);
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        json_response(['error' => 'Помилка при створенні пасти.'], 500);
    }
}

/**
 * Видалення пасти
 */
function handleDelete($id, $user) {
    if (!$id) json_response(['error' => 'ID пасти обов\'язковий.'], 400);
    
    $paste = Paste::findById($id);
    if (!$paste) json_response(['error' => 'Пасту не знайдено.'], 404);
    
    if ($paste->user_id !== $user->id && $user->role !== 'admin') {
        json_response(['error' => 'У вас немає прав для видалення цієї пасти.'], 403);
    }
    
    $paste->delete();
    json_response(['success' => true, 'message' => 'Пасту видалено.']);
}
