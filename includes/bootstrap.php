<?php
/**
 * Bootstrap-файл для ініціалізації додатку.
 * Централізує управління сесіями та загальні підключення.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Paste.php';
require_once __DIR__ . '/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Отримати поточного авторизованого користувача.
 * @return User|null
 */
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        // Перевірка на __PHP_Incomplete_Class
        if (isset($_SESSION['_user_cache']) && !($_SESSION['_user_cache'] instanceof User)) {
            unset($_SESSION['_user_cache']);
        }
        if (isset($_SESSION['_user_cache'])) {
            // Оновлюємо тільки баланс, щоб він завжди був актуальним (1 раз за запит)
            static $balance_updated = false;
            if (!$balance_updated) {
                $pdo = DB::getInstance()->getPDO();
                $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $res = $stmt->fetchColumn();
                if ($res !== false) {
                    $_SESSION['_user_cache']->credits = (int)$res;
                }
                $balance_updated = true;
            }
            return $_SESSION['_user_cache'];
        }
        if (!isset($_SESSION['_user_cache'])) {
            $user = User::findById($_SESSION['user_id']);
            if ($user) {
                $user->password_hash = null; // Не зберігаємо хеш пароля в сесії для безпеки
            }
            $_SESSION['_user_cache'] = $user;
        }
        return $_SESSION['_user_cache'];
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

// Глобальна перевірка верифікації пошти
$current_page = basename($_SERVER['PHP_SELF']);
$allowed_unverified_pages = ['verify.php', 'login.php'];

if (isset($_SESSION['user_id'])) {
    $user = getCurrentUser();
    if ($user && !$user->email_verified && !in_array($current_page, $allowed_unverified_pages)) {
        redirect('verify.php');
    }
}

