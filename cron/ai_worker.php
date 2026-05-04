<?php
/**
 * AI Worker для фонового перефразування паст.
 * Цей скрипт знаходить пасти з прапорцем is_pending_rewrite і обробляє їх через ШІ.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/Moderation.php';

// Щоб скрипт не вбивався сервером при довгому очікуванні ШІ
set_time_limit(600); 

$pdo = DB::getInstance()->getPDO();

// Знаходимо пасти для обробки (беремо по 5 за раз, щоб не перевантажувати)
$stmt = $pdo->query("SELECT * FROM pastes WHERE is_pending_rewrite = 1 ORDER BY created_at ASC LIMIT 5");
$pastes_to_process = $stmt->fetchAll();

if (empty($pastes_to_process)) {
    exit;
}

foreach ($pastes_to_process as $row) {
    $paste_id = $row['id'];
    $original_content = $row['content'];

    // Виклик ШІ для перефразування
    // Примітка: Moderation::rewrite вже містить CURL з таймаутом
    $rewritten_content = Moderation::rewrite($original_content);

    // Оновлюємо пасту: вставляємо новий текст і знімаємо прапорець черги
    $updateStmt = $pdo->prepare("
        UPDATE pastes 
        SET content = ?, is_pending_rewrite = 0 
        WHERE id = ?
    ");
    $updateStmt->execute([$rewritten_content, $paste_id]);
    
    // Оновлюємо теги, оскільки контент змінився
    $paste = Paste::findById($paste_id);
    if ($paste) {
        $paste->syncTags();
    }
}
