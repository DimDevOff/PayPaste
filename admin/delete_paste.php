<?php
include_once "check_admin.php"; // Перевірка, що це точно адмін
include_once "../config/db.php";
include_once "../includes/models/Paste.php";
require_once "../includes/csrf.php";

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
