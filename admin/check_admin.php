<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Session fixation protection: regenerate session ID BEFORE any authorization check
session_regenerate_id(true);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Не адмін — викидаємо на головну
    header("Location: ../index.php");
    exit();
}

// Verify the admin user still exists in the database (defense against stale sessions)
$user = User::findById($_SESSION['user_id']);
if (!$user || $user->role !== 'admin') {
    // User was deleted or demoted — clear session and redirect
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit();
}
?>
