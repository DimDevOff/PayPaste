<?php
/**
 * Bootstrap-файл для ініціалізації додатку.
 * Централізує управління сесіями та загальні підключення.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Загальні підключення
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Paste.php';
require_once __DIR__ . '/csrf.php';

/**
 * Отримати поточного авторизованого користувача.
 * @return User|null
 */
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return User::findById($_SESSION['user_id']);
    }
    return null;
}

/**
 * Перенаправлення на вказану адресу та вихід.
 * @param string $location
 */
function redirect($location) {
    header("Location: $location");
    exit;
}
