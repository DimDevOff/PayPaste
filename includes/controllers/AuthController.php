<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // Перевірка статусу сесії

require_once __DIR__ . '/../models/User.php'; // Завантаження моделі користувача
require_once __DIR__ . '/../services/AuthService.php'; // Завантаження AuthService
require_once __DIR__ . '/../csrf.php'; // Завантаження CSRF
require_once __DIR__ . '/../RateLimiter.php'; // Завантаження RateLimiter
require_once __DIR__ . '/../mailer.php'; // Завантаження Mailer (для enqueue)

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
    Email-верифікація ставиться у чергу (асинхронно).
    */
    private function register($email, $password, $confirm, $nickname) {
        if (!RateLimiter::check('register:' . $_SERVER['REMOTE_ADDR'], 5, 60)) {
            $_SESSION['error'] = "Занадто багато реєстрацій. Спробуйте пізніше.";
            header("Location: login.php");
            exit;
        }

        $_SESSION['old_input'] = ['email' => $email, 'nickname' => $nickname];

        try {
            $user = AuthService::register($email, $password, $confirm, $nickname);

            // Генеруємо код та ставимо відправку email у чергу (асинхронно)
            $code = AuthService::generateVerificationCode($user);
            $emailResult = Mailer::enqueueVerificationEmail($user->email, $code);

            AuthService::setSession($user);
            unset($_SESSION['old_input']);

            if ($emailResult['success']) {
                $_SESSION['success'] = "Реєстрація майже завершена! Вам нараховано 100 кредитів. Код підтвердження надсилається на вашу пошту.";
            } else {
                // Черга не прийняла задачу (rate limit або помилка), але користувач вже створений
                $_SESSION['warning'] = "Реєстрація завершена! Однак не вдалося поставити лист у чергу: " . ($emailResult['error'] ?? 'невідома помилка');
                $_SESSION['info'] = "Ви можете запросити повторну відправку коду на сторінці верифікації.";
            }

            header("Location: verify.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: login.php");
            exit;
        }
    }
    /*
    Функція входу.
    Перед початком роботи перевіряє чи всі поля заповнені.
    Потім перевіряє чи email або пароль коректні.
    Якщо всі перевірки пройдені, то користувач входить.
    */
    private function login($email, $password, $remember = false) {
        if (!RateLimiter::check('login:' . $_SERVER['REMOTE_ADDR'], 5, 60)) {
            $_SESSION['error'] = "Занадто багато спроб входу. Спробуйте пізніше.";
            header("Location: login.php");
            exit;
        }

        $_SESSION['old_input'] = ['email' => $email];

        try {
            $user = AuthService::login($email, $password, $remember);
            unset($_SESSION['old_input']);
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: login.php");
            exit;
        }
    }

    /*
    Функція виходу.
    Перед початком роботи знищує сесію.
    Якщо все пройшло успішно, то користувач перенаправляється на головну сторінку.
    */
    private function logout() {
        AuthService::logout();
        header("Location: index.php");
        exit;
    }
}
