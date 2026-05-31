<?php
require_once __DIR__ . "/check_admin.php"; // Перевірка, що це точно адмін
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/models/User.php";
require_once __DIR__ . "/../includes/services/AuthService.php";
require_once __DIR__ . "/../includes/csrf.php";

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

$user_id = $_POST['id'] ?? ''; // Отримуємо ID користувача з POST

// 3. Запобігання масовому видаленню (mass-deletion vulnerabilities) та валідація ID
// ID користувача має бути строго рядком
if (!is_string($user_id)) {
    $_SESSION['error'] = 'Некоректний формат ID користувача.';
    header('Location: users.php');
    exit();
}

// Перевірка суворого формату ID користувача: префікс u_ та 13 hex символів
if (!preg_match('/^u_[0-9a-fA-F]{13}$/', $user_id)) {
    $_SESSION['error'] = 'Невалідний ідентифікатор користувача.';
    header('Location: users.php');
    exit();
}

// 4. Перевірка, чи не намагається адміністратор видалити сам себе
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $user_id) {
    $_SESSION['error'] = 'Ви не можете видалити власний акаунт адміністратора!';
    header('Location: users.php');
    exit();
}

// 5. Перевірка наявності користувача в базі даних перед видаленням
$pdo = DB::getInstance()->getPDO();
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = 'Користувача не знайдено або його вже було видалено.';
    header('Location: users.php');
    exit();
}

// Якщо всі перевірки пройшли успішно — видаляємо користувача
try {
    if (AuthService::deleteByAdmin($user_id)) {
        AuditLog::log($_SESSION['user_id'], 'delete_user', $user_id);
        $_SESSION['success'] = 'Користувача успішно видалено.';
    } else {
        $_SESSION['error'] = 'Не вдалося видалити користувача.';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Помилка під час видалення користувача: ' . htmlspecialchars($e->getMessage());
}

// Повертаємо адміна назад до списку
header('Location: users.php');
exit();
?>
