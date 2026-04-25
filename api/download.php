<?php
/**
 * Скрипт безпечного завантаження файлів паст.
 * Перевіряє права доступу користувача перед віддачею файлу.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/models/Paste.php';
require_once __DIR__ . '/../includes/models/User.php';

$paste_id = $_GET['id'] ?? null;

if (!$paste_id) {
    http_response_code(400);
    die("ID пасти не вказано.");
}

$paste = Paste::findById($paste_id);

if (!$paste) {
    http_response_code(404);
    die("Пасту не знайдено.");
}

// Перевірка терміну дії
if ($paste->isExpired()) {
    http_response_code(404);
    die("Термін дії пасти закінчився.");
}

// Перевірка доступу (приватність та оплата)
$has_access = false;
$user_id = $_SESSION['user_id'] ?? null;

if (!$paste->is_private && !$paste->is_paid) {
    $has_access = true; // Публічна безкоштовна паста
} elseif ($user_id) {
    $user = User::findById($user_id);
    if ($user && ($user->id === $paste->user_id || $user->hasUnlocked($paste_id))) {
        $has_access = true; // Автор або той, хто розблокував
    }
}

if (!$has_access) {
    http_response_code(403);
    die("У вас немає доступу до цього файлу.");
}

// Пошук файлу в папці data/uploads/
$uploadDir = __DIR__ . '/../data/uploads/';
$files = glob($uploadDir . $paste->id . '.*');

if (empty($files)) {
    http_response_code(404);
    die("Файл не знайдено.");
}

$filePath = $files[0];
$fileName = basename($filePath);
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Визначення MIME-типу
$mimeTypes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'pdf'  => 'application/pdf',
    'zip'  => 'application/zip',
    'txt'  => 'text/plain'
];

$contentType = $mimeTypes[$fileExt] ?? 'application/octet-stream';

// Відправка заголовків
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));

// Якщо це не зображення, змушуємо завантажувати
if (!in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
} else {
    header('Content-Disposition: inline; filename="' . $fileName . '"');
}

// Віддача контенту файлу
readfile($filePath);
exit;
