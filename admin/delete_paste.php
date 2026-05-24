<?php
require_once __DIR__ . "/check_admin.php"; // Перевірка, що це точно адмін
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/models/Paste.php";
require_once __DIR__ . "/../includes/services/PasteService.php";
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

// 2. Перевірка валідності CSRF-токена
if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = 'Помилка безпеки (CSRF). Спробуйте ще раз.';
    header('Location: pastes.php');
    exit();
}

// Регенеруємо токен після перевірки для запобігання replay-атакам
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
$pdo = DB::getInstance()->getPDO();
$stmt = $pdo->prepare("SELECT id FROM pastes WHERE id = ?");
$stmt->execute([$paste_id]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = 'Пасту не знайдено або її вже було видалено.';
    header('Location: pastes.php');
    exit();
}

// Якщо всі перевірки пройшли успішно — видаляємо пасту
try {
    PasteService::delete($paste_id, null); // null = адміністратор
    AuditLog::log($_SESSION['user_id'], 'delete_paste', $paste_id);
    $_SESSION['success'] = 'Пасту успішно видалено.';
} catch (Exception $e) {
    $_SESSION['error'] = 'Помилка під час видалення пасти: ' . htmlspecialchars($e->getMessage());
}

// Повертаємо адміна назад до списку
header('Location: pastes.php');
exit();
?>
