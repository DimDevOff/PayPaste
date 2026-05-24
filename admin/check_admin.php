<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Якщо не адмін - викидаємо на головну
    header("Location: ../index.php"); 
    exit();
}
// Регенерація session_id для захисту від session fixation в адмін-панелі
session_regenerate_id(true);
?>
