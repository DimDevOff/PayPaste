<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // Перевірка статусу сесії
require_once __DIR__ . '/../models/User.php'; // Завантаження моделі користувача
require_once __DIR__ . '/../models/Paste.php'; // Завантаження моделі пасти
require_once __DIR__ . '/../csrf.php'; // Завантаження CSRF

class SettingsController {
    /*
    Функція обробки запитів налаштувань.
    Перед початком роботи перевіряє метод запиту та CSRF токен.
    Потім визначає дію: оновлення профілю, видалення пасти, зміна приватності або видалення акаунту.
    Якщо дію розпізнано, викликає відповідний метод.
    */
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action = $_POST['action'] ?? '';

            if ($action === 'update_profile') {
                $this->updateProfile($_POST['nickname'] ?? '', $_POST['password'] ?? '', $_POST['password_confirm'] ?? '');
            } elseif ($action === 'delete_paste') {
                $this->deletePaste($_POST['paste_id'] ?? '');
            } elseif ($action === 'toggle_visibility') {
                $this->toggleVisibility($_POST['paste_id'] ?? '');
            } elseif ($action === 'unlink_account') {
                $this->unlinkAccount($_POST['provider'] ?? '');
            } elseif ($action === 'update_theme') {
                $this->updateTheme($_POST['theme'] ?? 'retro');
            } elseif ($action === 'delete_account') {
                $this->deleteAccount();
            } elseif ($action === 'generate_api_key') {
                $this->generateApiKey();
            }
        }
    }

    /*
    Функція видалення пасти власником.
    Перед початком роботи перевіряє чи вказано ID пасти.
    Потім перевіряє чи паста існує та чи належить вона поточному користувачу.
    Якщо перевірки пройдені, то паста видаляється з бази.
    */
    private function deletePaste($paste_id) {
        if (empty($paste_id)) {
            $_SESSION['error'] = "Не вказано ID пасти!";
            header("Location: settings.php");
            exit;
        }

        $paste = Paste::findById($paste_id);
        if (!$paste || $paste->user_id !== $_SESSION['user_id']) {
            $_SESSION['error'] = "Пасту не знайдено або у вас немає прав!";
            header("Location: settings.php");
            exit;
        }

        $paste->delete();
        $_SESSION['success'] = "Пасту \"" . htmlspecialchars($paste->title) . "\" видалено!";
        header("Location: settings.php");
        exit;
    }

    /*
    Функція зміни приватності пасти.
    Перед початком роботи перевіряє чи вказано ID пасти.
    Потім перевіряє права власності на пасту.
    Потім інвертує стан приватності (публічна -> приватна і навпаки).
    Якщо все пройшло успішно, зберігає зміни.
    */
    private function toggleVisibility($paste_id) {
        if (empty($paste_id)) {
            $_SESSION['error'] = "Не вказано ID пасти!";
            header("Location: settings.php");
            exit;
        }

        $paste = Paste::findById($paste_id);
        if (!$paste || $paste->user_id !== $_SESSION['user_id']) {
            $_SESSION['error'] = "Пасту не знайдено або у вас немає прав!";
            header("Location: settings.php");
            exit;
        }

        $paste->is_private = !$paste->is_private;
        $paste->save();
        $status = $paste->is_private ? 'приватною' : 'публічною';
        $_SESSION['success'] = "Пасту \"" . htmlspecialchars($paste->title) . "\" зроблено {$status}!";
        header("Location: settings.php");
        exit;
    }

    /*
    Функція оновлення профілю користувача.
    Перед початком роботи перевіряє коректність нікнейма.
    Потім, якщо вказано новий пароль, перевіряє його довжину та підтвердження.
    Потім хешує новий пароль та оновлює нікнейм у об'єкті користувача.
    Якщо всі дані валідні, зберігає оновлення в базі.
    */
    private function updateProfile($nickname, $password, $confirm) {
        $user = User::findById($_SESSION['user_id']);
        if (!$user) {
            $_SESSION['error'] = "Користувача не знайдено!";
            return;
        }

        $nickname = htmlspecialchars(trim($nickname));
        $password = trim($password);
        $confirm = trim($confirm);

        if (empty($nickname)) {
            $_SESSION['error'] = "Нікнейм не може бути порожнім!";
            return;
        }

        if (mb_strlen($nickname) > 50) {
            $_SESSION['error'] = "Нікнейм занадто довгий!";
            return;
        }

        $user->nickname = $nickname;

        if (!empty($password)) {
            if (mb_strlen($password) < 6) {
                $_SESSION['error'] = "Пароль має містити мінімум 6 символів!";
                return;
            }
            if ($password !== $confirm) {
                $_SESSION['error'] = "Паролі не співпадають!";
                return;
            }
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        }

        $user->save();
        $_SESSION['success'] = "Профіль успішно оновлено!";
        header("Location: settings.php");
        exit;
    }

    /*
    Функція зміни кольорової теми інтерфейсу.
    Перевіряє чи обрана тема дозволена, зберігає вибір у БД.
    */
    private function updateTheme($theme) {
        $user = User::findById($_SESSION['user_id']);
        if (!$user) {
            $_SESSION['error'] = "Користувача не знайдено!";
            header("Location: settings.php");
            exit;
        }

        $user->setTheme($theme);
        $_SESSION['success'] = "Тему змінено! 🎨";
        header("Location: settings.php");
        exit;
    }

    /*
    Функція від'єднання сторонніх акаунтів (OAuth).
    Перед початком роботи шукає користувача в системі.
    Потім скидає ID відповідного провайдера (GitHub або Telegram).
    Якщо зміни внесені, зберігає оновлений профіль.
    */
    private function unlinkAccount($provider) {
        $user = User::findById($_SESSION['user_id']);
        if (!$user) return;

        if ($provider === 'github') {
            $user->github_id = null;
        } elseif ($provider === 'telegram') {
            $user->telegram_id = null;
        }

        $user->save();
        $_SESSION['success'] = "Акаунт " . ucfirst($provider) . " від'єднано!";
        header("Location: settings.php");
        exit;
    }

    /*
    Функція повного видалення акаунту.
    Перед початком роботи перевіряє підтвердження через пароль, Passkey або OAuth.
    Потім видаляє всі дані користувача з системи.
    Потім повністю знищує сесію та перенаправляє на головну.
    Якщо підтвердження не отримано, видає помилку безпеки.
    */
    private function deleteAccount() {
        $user = User::findById($_SESSION['user_id']);
        if (!$user) {
            $_SESSION['error'] = "Користувача не знайдено!";
            return;
        }

        $password = $_POST['password'] ?? '';
        $passkeyConfirmed = $_SESSION['passkey_confirmed_delete'] ?? false;
        $oauthConfirmed = $_SESSION['oauth_confirmed_delete'] ?? false;

        // Перевірка або пароля, або підтвердження через Passkey/OAuth
        if ($passkeyConfirmed || $oauthConfirmed || (!empty($password) && password_verify($password, $user->password_hash))) {
            $user->delete();
            
            // Очищення cookie
            setcookie('remember_me', '', time() - 3600, '/');
            setcookie('remember_email', '', time() - 3600, '/');
            
            // Очищення та перезапуск сесії
            $_SESSION = [];
            session_destroy();
            session_start();
            $_SESSION['success'] = "Ваш акаунт було видалено. Нам дуже шкода! 😢";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error'] = "Невірний пароль або Passkey не підтверджено!";
            header("Location: settings.php");
            exit;
        }
    }

    /**
     * Генерує новий API-ключ для користувача
     */
    private function generateApiKey() {
        $user = User::findById($_SESSION['user_id']);
        if (!$user) return;

        $key = $user->generateApiKey();
        $_SESSION['success'] = "Новий API-ключ згенеровано! Збережіть його в надійному місці.";
        header("Location: settings.php");
        exit;
    }
}
