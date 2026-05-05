<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // Перевірка статусу сесії

// Авто-логін та оновлення кукі на 2 тижні
require_once __DIR__ . '/services/AuthService.php';
if (isset($_COOKIE['remember_me'])) {
    AuthService::checkRememberMe();
}

if (empty($_SESSION['csrf_token'])) { // Генерація токена
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf() { // Перевірка токена
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $_SESSION['error'] = 'Помилка безпеки (CSRF). Спробуйте ще раз.';
            $allowed_redirects = ['index.php', 'create.php', 'login.php', 'settings.php', 'view.php'];
            $referer = basename(parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH));
            $fallback = in_array($referer, $allowed_redirects) ? $referer : 'index.php';
            header("Location: " . $fallback);
            exit;
        }
        // Регенерація токена після успішної перевірки
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function csrf_field() { // Повернення токена
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

