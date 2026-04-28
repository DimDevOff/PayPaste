<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // Перевірка статусу сесії

require_once __DIR__ . '/../models/User.php'; // Завантаження моделі користувача
require_once __DIR__ . '/../csrf.php'; // Завантаження CSRF

class AuthController { // Клас контролера авторизації
    public function handleRequest() { // Обробка запитів
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            if (isset($_POST['action']) && $_POST['action'] === 'register') { // Реєстрація
                $this->register($_POST['email'], $_POST['password'], $_POST['password_confirm'] ?? '', $_POST['nickname'] ?? 'Anon');
            } elseif (isset($_POST['action']) && $_POST['action'] === 'login') { // Вхід
                $this->login($_POST['email'], $_POST['password'], isset($_POST['remember']));
            } elseif (isset($_POST['action']) && $_POST['action'] === 'logout') { // Вихід
                $this->logout();
            }
        }
    }
    /*
    Функція реєстрації.
    Перед початком роботи перевіряє чи всі поля заповнені.
    Потім перевіряє чи email коректний.
    Потім перевіряє чи пароль має мінімум 6 символів.
    Потім перевіряє чи нікнейм не занадто довгий.
    Потім перевіряє чи паролі співпадають.
    Потім перевіряє чи користувач з таким email вже існує.
    Якщо всі перевірки пройдені, то користувач реєструється.
    */
    private function register($email, $password, $confirm, $nickname) {
        $_SESSION['old_input'] = ['email' => $email, 'nickname' => $nickname];
        
        $email = trim($email);
        
        if (empty($email) || empty($password) || empty($confirm)) {
            $_SESSION['error'] = "Всі поля обов'язкові!";
            header("Location: login.php");
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Некоректний формат email!";
            header("Location: login.php");
            exit;
        }

        if (mb_strlen($password) < 6) {
            $_SESSION['error'] = "Пароль має містити мінімум 6 символів!";
            header("Location: login.php");
            exit;
        }

        if (mb_strlen($nickname) > 50) {
            $_SESSION['error'] = "Нікнейм занадто довгий!";
            header("Location: login.php");
            exit;
        }

        if ($password !== $confirm) {
            $_SESSION['error'] = "Паролі не співпадають!";
            header("Location: login.php");
            exit;
        }

        $existingUser = User::findByEmail($email);
        if ($existingUser) {
            $_SESSION['error'] = "Користувач з таким email вже існує!";
            return;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $user = new User($email, $hashed, $nickname, 100);
        $user->save();

        $_SESSION['user_id'] = $user->id;
        $_SESSION['role'] = $user->role;
        unset($_SESSION['old_input']);
        $_SESSION['success'] = "Реєстрація успішна! Вам нараховано 100 кредитів.";
        header("Location: index.php");
        exit;
    }
    /*
    Функція входу.
    Перед початком роботи перевіряє чи всі поля заповнені.
    Потім перевіряє чи email або пароль коректні.
    Якщо всі перевірки пройдені, то користувач входить.
    */
    private function login($email, $password, $remember = false) {
        $_SESSION['old_input'] = ['email' => $email];
        
        $email = trim($email);
        
        if (empty($email) || empty($password)) {
            $_SESSION['error'] = "Введіть email та пароль!";
            header("Location: login.php");
            exit;
        }

        $user = User::findByEmail($email);
        if ($user && password_verify($password, $user->password_hash)) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['role'] = $user->role;
            unset($_SESSION['old_input']);
            
            // Робота з кукі (авторизація на 14 днів)
            if ($remember) {
                // Створюємо захищений токен: id + хеш
                $cookie_secret = COOKIE_SECRET ?: 'Fallback_Secret';
                $token = $user->id . ':' . hash_hmac('sha256', $user->id . $user->password_hash, $cookie_secret);
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie('remember_me', $token, [
                    'expires' => time() + 14 * 24 * 3600,
                    'path' => '/',
                    'httponly' => true,
                    'secure' => $secure,
                    'samesite' => 'Lax'
                ]);
                setcookie('remember_email', $email, [
                    'expires' => time() + 14 * 24 * 3600,
                    'path' => '/',
                    'httponly' => false,
                    'secure' => $secure,
                    'samesite' => 'Lax'
                ]);
            } else {
                setcookie('remember_me', '', time() - 3600, '/');
                setcookie('remember_email', '', time() - 3600, '/');
            }
            
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error'] = "Невірний email або пароль!";
        }
    }

    /*
    Функція виходу.
    Перед початком роботи знищує сесію.
    Якщо все пройшло успішно, то користувач перенаправляється на головну сторінку.
    */
    private function logout() {
        // Очищення remember_me cookie при виході
        setcookie('remember_me', '', time() - 3600, '/');
        setcookie('remember_email', '', time() - 3600, '/');
        session_destroy();
        header("Location: index.php");
        exit;
    }
}

