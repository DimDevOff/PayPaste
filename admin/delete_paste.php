<?php
require_once __DIR__ . "/check_admin.php"; // Перевірка, що це точно адмін

// 1. Перевірка, що метод запиту є POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    exit("Метод не дозволений.");
}

// Додатковий жорсткий захист на роль адміна
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    exit("Доступ заборонено.");
}

// 2. CSRF перевірка (стандартизована)
verify_csrf();

$paste_id = $_POST['id'] ?? ''; // Отримуємо ID пасти з POST

// 3. Запобігання масовому видаленню (mass-deletion vulnerabilities) та валідація ID
// ID пасти має бути строго рядком
if (!is_string($paste_id)) {
    $_SESSION['error'] = 'Некоректний формат ID пасти.';
    header('Location: pastes.php');
    exit();
}

// Перевірка суворого формату ID пасти: префікс p_ та 16 hex символів
if (!preg_match('/^p_[0-9a-fA-F]{16}$/', $paste_id)) {
    $_SESSION['error'] = 'Невалідний ідентифікатор пасти.';
    header('Location: pastes.php');
    exit();
}

// 4. Перевірка наявності пасти в базі даних перед видаленням
$pasteExists = Repo::pastes()->findById($paste_id);
if (!$pasteExists) {
    $_SESSION['error'] = 'Пасту не знайдено або її вже було видалено.';
    header('Location: pastes.php');
    exit();
}

// Якщо всі перевірки пройшли успішно — видаляємо пасту
try {
    PasteService::delete($paste_id, $user); // $user завантажено в check_admin.php, це адмін
    AuditLog::log($_SESSION['user_id'], 'delete_paste', $paste_id);
    $_SESSION['success'] = 'Пасту успішно видалено.';
} catch (Exception $e) {
    $_SESSION['error'] = 'Помилка під час видалення пасти: ' . htmlspecialchars($e->getMessage());
}

// Повертаємо адміна назад до списку
header('Location: pastes.php');
exit();
?>
