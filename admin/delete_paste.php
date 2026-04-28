<?php
include_once __DIR__ . "/check_admin.php"; // Перевірка, що це точно адмін
include_once __DIR__ . "/../config/db.php";
include_once __DIR__ . "/../includes/models/Paste.php";
require_once __DIR__ . "/../includes/csrf.php";

verify_csrf();

$paste_id = $_POST['id'] ?? ''; // Отримуємо ID пасти з POST

// Захист від ін'єкцій та перевірка на порожнечу
if (!empty($paste_id)) {
    // Викликаємо функцію з моделі
    Paste::delete_paste_by_admin($paste_id); 
}

// Повертаємо адміна назад до списку
header('Location: pastes.php');
exit();
?>

