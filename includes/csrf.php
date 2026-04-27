<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // Перевірка статусу сесії

// Авто-логін та оновлення кукі на 2 тижні
if (isset($_COOKIE['remember_me'])) {
    require_once __DIR__ . '/models/User.php';
    $parts = explode(':', $_COOKIE['remember_me']);
    if (count($parts) === 2) {
        list($uid, $hash) = $parts;
        $user = User::findById($uid);
        $cookie_secret = getenv('COOKIE_SECRET') ?: 'Fallback_Secret';
        if ($user) {
            $expected = hash_hmac('sha256', $user->id . $user->password_hash, $cookie_secret);
            if (hash_equals($expected, $hash)) {
                // Якщо сесії немає - авторизуємо
                if (!isset($_SESSION['user_id'])) {
                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['role'] = $user->role;
                }
                // Оновлюємо термін дії ще на 14 днів при кожному заході
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie('remember_me', $_COOKIE['remember_me'], [
                    'expires' => time() + 14 * 24 * 3600,
                    'path' => '/',
                    'httponly' => true,
                    'secure' => $secure,
                    'samesite' => 'Lax'
                ]);
                if (isset($_COOKIE['remember_email'])) {
                    setcookie('remember_email', $_COOKIE['remember_email'], [
                        'expires' => time() + 14 * 24 * 3600,
                        'path' => '/',
                        'httponly' => false, // Потрібен доступ з JS для auto-fill
                        'secure' => $secure,
                        'samesite' => 'Lax'
                    ]);
                }
            } else {
                setcookie('remember_me', '', time() - 3600, '/'); // Токен невалідний
            }
        }
    }
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
