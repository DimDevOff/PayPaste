<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // Перевірка статусу сесії
require_once __DIR__ . '/../models/User.php'; // Завантаження моделі користувача
require_once __DIR__ . '/../models/Paste.php'; // Завантаження моделі пасти
require_once __DIR__ . '/../services/AuthService.php'; // Завантаження AuthService
require_once __DIR__ . '/../services/PasteService.php'; // Завантаження PasteService
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
                $this->updateProfile($_POST['nickname'] ?? '', $_POST['password'] ?? '', $_POST['password_confirm'] ?? '', $_POST['email'] ?? '');
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

        try {
            PasteService::delete($paste_id, $_SESSION['user_id']);
            $_SESSION['success'] = "Пасту видалено!";
            header("Location: settings.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: settings.php");
            exit;
        }
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

        try {
            $isPrivate = PasteService::toggleVisibility($paste_id, $_SESSION['user_id']);
            $status = $isPrivate ? 'приватною' : 'публічною';
            $_SESSION['success'] = "Пасту зроблено {$status}!";
            header("Location: settings.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: settings.php");
            exit;
        }
    }

    /*
    Функція оновлення профілю користувача.
    Перед початком роботи перевіряє коректність нікнейма.
    Потім, якщо вказано новий пароль, перевіряє його довжину та підтвердження.
    Потім хешує новий пароль та оновлює нікнейм у об'єкті користувача.
    Якщо всі дані валідні, зберігає оновлення в базі.
    */
    private function updateProfile($nickname, $password, $confirm, $new_email = '') {
        $user = User::findById($_SESSION['user_id']);
        if (!$user) {
            $_SESSION['error'] = "Користувача не знайдено!";
            return;
        }

        try {
            $result = AuthService::updateProfile($user, $nickname, $password, $confirm, $new_email);

            if ($result['email_changed']) {
                require_once __DIR__ . '/../mailer.php';
                Mailer::sendEmailChangedNotification($user->email, $new_email);

                $code = $user->generateVerificationCode();
                Mailer::sendVerificationEmail($user->email, $code);

                $_SESSION['success'] = "Профіль оновлено! На нову пошту надіслано код підтвердження.";
                header("Location: verify.php");
                exit;
            } else {
                $_SESSION['success'] = "Профіль успішно оновлено!";
                header("Location: settings.php");
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: settings.php");
            exit;
        }
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

        $allowed = ['retro', 'dark', 'terminal', 'light', 'github-orange', 'retro-green'];
        if (!in_array($theme, $allowed)) {
            $theme = 'retro';
        }
        $user->theme = $theme;
        $user->save();
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

        try {
            AuthService::unlinkOAuth($user, $provider);
            $_SESSION['success'] = "Акаунт " . ucfirst($provider) . " від'єднано!";
            header("Location: settings.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: settings.php");
            exit;
        }
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
            try {
                AuthService::deleteAccount($user);
                $_SESSION['success'] = "Ваш акаунт було видалено. Нам дуже шкода! 😢";
                header("Location: index.php");
                exit;
            } catch (Exception $e) {
                $_SESSION['error'] = "Помилка видалення акаунта: " . $e->getMessage();
                header("Location: settings.php");
                exit;
            }
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

        $key = AuthService::generateApiKey($user);
        $_SESSION['success'] = "Новий API-ключ згенеровано! Збережіть його в надійному місці.";
        header("Location: settings.php");
        exit;
    }
}
