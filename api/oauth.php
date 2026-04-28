<?php
/**
 * Файл управління OAuth-авторизацією (GitHub та Telegram).
 * 
 * Цей скрипт обробляє:
 * 1. Вхід через сторонні сервіси (GitHub, Telegram).
 * 2. Прив'язку їх до існуючого акаунту.
 * 3. Підтвердження видалення акаунта через OAuth-провайдерів.
 * 4. Автоматичну реєстрацію нових користувачів з початковим балансом 100 кредитів.
 * 
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/models/User.php';

$provider = $_GET['provider'] ?? 'unknown';

// GITHUB
if ($provider === 'github') {

    $client_id = GITHUB_CLIENT_ID;
    $client_secret = GITHUB_CLIENT_SECRET;
    $app_url = rtrim(APP_URL ?: 'https://YOUR_DOMAIN', '/');
    $redirect_uri = $app_url . '/api/oauth.php?provider=github';

    // Крок 1: Перенаправлення користувача на сторінку авторизації GitHub
    if (!isset($_GET['code'])) {
        if (empty($client_id)) {
            die('GITHUB_CLIENT_ID не налаштований у .env');
        }
        
        $url = "https://github.com/login/oauth/authorize?" . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => 'read:user user:email'
        ]);
        
        header("Location: $url");
        exit;
    }

    $code = $_GET['code'];

    // Крок 2: Обмін коду на Access Token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://github.com/login/oauth/access_token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code,
        'redirect_uri' => $redirect_uri
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        die('Не вдалося отримати токен доступу від GitHub.');
    }
    $access_token = $data['access_token'];

    // Крок 3: Отримання даних про користувача через API GitHub
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/user");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "User-Agent: PayPaste-App",
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $user_json = curl_exec($ch);
    curl_close($ch);

    $github_user = json_decode($user_json, true);

    if (empty($github_user['id'])) {
        die('Не вдалося завантажити інформацію про користувача з GitHub.');
    }

    $github_id = $github_user['id'];
    $nickname = $github_user['login'];
    $email = current(array_filter([$github_user['email'] ?? null]));

    if (!$email) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.github.com/user/emails");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token",
            "User-Agent: PayPaste-App",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $emails_json = curl_exec($ch);
        curl_close($ch);
        
        $emails_data = json_decode($emails_json, true);
        if (is_array($emails_data)) {
            foreach ($emails_data as $e) {
                if ($e['primary'] && $e['verified']) {
                    $email = $e['email'];
                    break;
                }
            }
        }
    }

    // Сценарій 1: користувач вже авторизований (прив'язка або видалення)
    if (isset($_SESSION['user_id'])) {
        $currentUser = User::findById($_SESSION['user_id']);

        // Підтвердження видалення акаунта через повторний вхід
        if (isset($_GET['confirm_delete_oauth'])) {
            if ($currentUser->github_id === $github_id) {
                $_SESSION['oauth_confirmed_delete'] = true;
                $_SESSION['success'] = "Видалення акаунта підтверджено через GitHub! Тепер ви можете натиснути 'Видалити акаунт'.";
            } else {
                $_SESSION['error'] = "Цей GitHub акаунт не збігається з прив'язаним до вашого профілю!";
            }
            header("Location: ../settings.php");
            exit;
        }

        $existingUserWithId = User::findByGithubId($github_id);

        if ($existingUserWithId) {
            if ($existingUserWithId->id === $currentUser->id) {
                $_SESSION['success'] = "Цей акаунт GitHub вже прив'язаний до вашого профілю!";
            } else {
                $_SESSION['error'] = "Цей акаунт GitHub вже прив'язаний до іншого профілю. Будь ласка, увійдіть у той акаунт, видаліть його, і спробуйте знову.";
            }
        } else {
            $currentUser->linkGithub($github_id);
            $_SESSION['success'] = "GitHub успішно прив'язано!";
        }
        header("Location: ../settings.php");
        exit;
    }

    // Сценарій 2: вхід або реєстрація
    $user = User::findByGithubId($github_id);

    if (!$user && $email) {
        $user = User::findByEmail($email);
        if ($user) {
            // Об'єднання ID
            $user->linkGithub($github_id);
        }
    }

    if (!$user) {
        if (!$email) {
            die('Не вдалося знайти підтверджену основну електронну адресу в GitHub, і не знайдено існуючого зв\'язку.');
        }
        $random_password = bin2hex(random_bytes(12)); // Пароль не потрібен для OAuth, але створюємо для безпеки
        $user = new User($email, password_hash($random_password, PASSWORD_DEFAULT), $nickname, 100, [], 'user', null, null, $github_id);
        $user->save();
    }

    $_SESSION['user_id'] = $user->id;
    $_SESSION['nickname'] = $user->nickname;
    $_SESSION['role'] = $user->role;

    header("Location: ../index.php");
    exit;
}

// TELEGRAM
if ($provider === 'telegram') {
    $bot_token = TELEGRAM_LOGIN_BOT_TOKEN ?: die('TELEGRAM_LOGIN_BOT_TOKEN не налаштований');
    
    $check_hash = $_GET['hash'] ?? null;
    
    if (!$check_hash || !isset($_GET['id'])) {
        die('Telegram вхід: відсутні обов\'язкові параметри (hash/id). Переконайтеся, що ви використовуєте офіційний віджет.');
    }
    
    $auth_data = $_GET;
    unset($auth_data['hash']);
    unset($auth_data['provider']);
    unset($auth_data['confirm_delete_oauth']);
    
    $data_check_arr = [];
    foreach ($auth_data as $key => $value) {
        $data_check_arr[] = $key . '=' . $value;
    }
    sort($data_check_arr);
    $data_check_string = implode("\n", $data_check_arr);
    
    $secret_key = hash('sha256', $bot_token, true);
    $hash = hash_hmac('sha256', $data_check_string, $secret_key);
    
    if (!hash_equals($hash, $check_hash)) {
        die('Telegram вхід: недійсна перевірка хешу');
    }
    
    $telegram_id = (int)$auth_data['id'];
    $nickname = $auth_data['username'] ?: 'TG_' . $telegram_id;

    // Сценарій 1: користувач вже авторизований (прив'язка або видалення)
    if (isset($_SESSION['user_id'])) {
        $currentUser = User::findById($_SESSION['user_id']);

        // Перевірка, чи це підтвердження видалення акаунта
        if (isset($_GET['confirm_delete_oauth'])) {
            if ($currentUser->telegram_id === $telegram_id) {
                $_SESSION['oauth_confirmed_delete'] = true;
                $_SESSION['success'] = "Видалення акаунта підтверджено через Telegram! Тепер ви можете натиснути 'Видалити акаунт'.";
            } else {
                $_SESSION['error'] = "Цей Telegram акаунт не збігається з прив'язаним до вашого профілю!";
            }
            header("Location: ../settings.php");
            exit;
        }

        $existingUserWithId = User::findByTelegramId($telegram_id);

        if ($existingUserWithId) {
            if ($existingUserWithId->id === $currentUser->id) {
                $_SESSION['success'] = "Цей Telegram вже прив'язаний до вашого профілю!";
            } else {
                $_SESSION['error'] = "Цей Telegram вже прив'язаний до іншого профілю. Будь ласка, увійдіть у той акаунт, видаліть його, і спробуйте знову.";
            }
        } else {
            $currentUser->linkTelegram($telegram_id);
            $_SESSION['success'] = "Telegram успішно прив'язано!";
        }
        header("Location: ../settings.php");
        exit;
    }
    
    // Сценарій 2: вхід або реєстрація
    $user = User::findByTelegramId($telegram_id);
    
    if (!$user) {
        $random_password = bin2hex(random_bytes(12));
        $user = new User(
            "telegram_{$telegram_id}@paypaste.local",
            password_hash($random_password, PASSWORD_DEFAULT),
            $nickname,
            100,
            [],
            'user',
            null,
            $telegram_id
        );
        $user->save();
    }
    
    $_SESSION['user_id'] = $user->id;
    $_SESSION['nickname'] = $user->nickname;
    $_SESSION['role'] = $user->role;
    
    header("Location: ../index.php");
    exit;
}

