<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Passkey.php';

/**
 * Сервіс для управління автентифікацією користувачів.
 * Відповідає за реєстрацію, вхід, вихід, remember_me, OAuth та Passkey логіку.
 */
class AuthService {

    /**
     * Реєструє нового користувача з валідацією.
     * Нараховує 100 стартових кредитів.
     *
     * @param string $email Email
     * @param string $password Пароль
     * @param string $confirm Підтвердження пароля
     * @param string $nickname Нікнейм
     * @return User Створений користувач
     * @throws Exception При помилці валідації або якщо email зайнятий
     */
    public static function register(string $email, string $password, string $confirm, string $nickname): User {
        $email = trim($email);
        $nickname = trim($nickname);
        $password = trim($password);
        $confirm = trim($confirm);

        if (empty($email) || empty($password) || empty($confirm)) {
            throw new Exception("Всі поля обов'язкові!");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Некоректний формат email!");
        }

        if (mb_strlen($password) < 6) {
            throw new Exception("Пароль має містити мінімум 6 символів!");
        }

        if (mb_strlen($nickname) > 50) {
            throw new Exception("Нікнейм занадто довгий!");
        }

        if ($password !== $confirm) {
            throw new Exception("Паролі не співпадають!");
        }

        $existing = User::findByEmail($email);
        if ($existing) {
            throw new Exception("Користувач з таким email вже існує!");
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $user = new User($email, $hashed, $nickname, 100);
        $user->save();

        return $user;
    }

    /**
     * Авторизує користувача за email та паролем.
     * Опціонально встановлює remember_me cookie.
     *
     * @param string $email Email
     * @param string $password Пароль
     * @param bool $remember Чи встановлювати remember_me cookie
     * @return User Авторизований користувач
     * @throws Exception При невірних даних
     */
    public static function login(string $email, string $password, bool $remember = false): User {
        $email = trim($email);

        if (empty($email) || empty($password)) {
            throw new Exception("Введіть email та пароль!");
        }

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, $user->password_hash)) {
            throw new Exception("Невірний email або пароль!");
        }

        self::setSession($user);

        if ($remember) {
            self::setRememberCookie($user);
        } else {
            self::clearRememberCookie();
        }

        return $user;
    }

    /**
     * Вихід користувача — очищення сесії та remember_me cookie.
     */
    public static function logout(): void {
        self::clearRememberCookie();
        session_destroy();
    }

    /**
     * Встановлює сесійні дані для користувача.
     *
     * @param User $user Користувач
     */
    public static function setSession(User $user): void {
        // Регенерація session_id для захисту від session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user->id;
        $_SESSION['role'] = $user->role;
        unset($_SESSION['old_input']);

        // Явне збереження сесії — гарантує що дані записані на диск
        session_write_close();
        // Знову відкриваємо — для подальших змін в цьому запиті (якщо є)
        session_start();
    }

    /**
     * Встановлює remember_me cookie для тривалої сесії (14 днів).
     *
     * @param User $user Користувач
     */
    public static function setRememberCookie(User $user): void {
        if (!defined('COOKIE_SECRET') || COOKIE_SECRET === '') {
            throw new \RuntimeException('COOKIE_SECRET не налаштовано в config.php');
        }
        $cookieSecret = COOKIE_SECRET;
        $token = $user->id . ':' . hash_hmac('sha256', $user->id . $user->password_hash, $cookieSecret);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie('remember_me', $token, [
            'expires' => time() + 14 * 24 * 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax'
        ]);
        setcookie('remember_email', $user->email, [
            'expires' => time() + 14 * 24 * 3600,
            'path' => '/',
            'httponly' => false,
            'secure' => $secure,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Очищує remember_me cookie.
     */
    public static function clearRememberCookie(): void {
        setcookie('remember_me', '', time() - 3600, '/');
        setcookie('remember_email', '', time() - 3600, '/');
    }

    /**
     * Перевіряє remember_me cookie та відновлює сесію.
     * Викликається з csrf.php при ініціалізації.
     *
     * @return User|null Відновлений користувач або null
     */
    public static function checkRememberMe(): ?User {
        if (!isset($_COOKIE['remember_me'])) {
            return null;
        }

        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) !== 2) {
            self::clearRememberCookie();
            return null;
        }

        list($uid, $hash) = $parts;
        $user = User::findById($uid);

        if (!$user) {
            self::clearRememberCookie();
            return null;
        }

        if (!defined('COOKIE_SECRET') || COOKIE_SECRET === '') {
            throw new \RuntimeException('COOKIE_SECRET не налаштовано в config.php');
        }
        $cookieSecret = COOKIE_SECRET;
        $expected = hash_hmac('sha256', $user->id . $user->password_hash, $cookieSecret);

        if (!hash_equals($expected, $hash)) {
            self::clearRememberCookie();
            return null;
        }

        // Оновлюємо термін дії cookie
        self::setRememberCookie($user);

        // Авторизуємо, якщо сесії ще немає
        if (!isset($_SESSION['user_id'])) {
            self::setSession($user);
        }

        return $user;
    }

    /**
     * Авторизує користувача через OAuth (GitHub/Telegram).
     * Якщо користувача не знайдено — створює нового.
     *
     * @param string $provider Провайдер (github, telegram)
     * @param array $oauthData Дані від провайдера (id, email, nickname)
     * @return User Авторизований користувач
     * @throws Exception При помилці
     */
    public static function oauthLogin(string $provider, array $oauthData): User {
        $providerId = $oauthData['id'] ?? null;
        $email = $oauthData['email'] ?? null;
        $nickname = $oauthData['nickname'] ?? 'OAuthUser';

        if (!$providerId) {
            throw new Exception("Відсутній ID від OAuth провайдера.");
        }

        $user = null;

        if ($provider === 'github') {
            $user = User::findByGithubId($providerId);
        } elseif ($provider === 'telegram') {
            $user = User::findByTelegramId($providerId);
        }

        // Якщо не знайдено за provider ID — шукаємо за email
        if (!$user && $email) {
            $user = User::findByEmail($email);
            if ($user) {
                // Прив'язуємо OAuth ID
                if ($provider === 'github') {
                    $user->github_id = $providerId;
                } elseif ($provider === 'telegram') {
                    $user->telegram_id = $providerId;
                }
                $user->save();
            }
        }

        // Створення нового користувача
        if (!$user) {
            if (!$email) {
                $email = $provider . '_' . $providerId . '@paypaste.local';
            }
            $randomPassword = bin2hex(random_bytes(12));
            $user = new User(
                $email,
                password_hash($randomPassword, PASSWORD_DEFAULT),
                $nickname,
                100,
                [],
                'user',
                null,
                $provider === 'telegram' ? $providerId : null,
                $provider === 'github' ? $providerId : null
            );
            $user->save();
        }

        self::setSession($user);
        self::setRememberCookie($user);
        return $user;
    }

    /**
     * Прив'язує OAuth акаунт до поточного користувача.
     *
     * @param User $user Поточний користувач
     * @param string $provider Провайдер (github, telegram)
     * @param string $providerId ID від провайдера
     * @return bool Успішність операції
     * @throws Exception Якщо ID вже прив'язаний до іншого акаунта
     */
    public static function linkOAuth(User $user, string $provider, string $providerId): bool {
        $existing = null;
        if ($provider === 'github') {
            $existing = User::findByGithubId($providerId);
        } elseif ($provider === 'telegram') {
            $existing = User::findByTelegramId($providerId);
        }

        if ($existing && $existing->id !== $user->id) {
            throw new Exception("Цей акаунт вже прив'язаний до іншого профілю.");
        }

        if ($provider === 'github') {
            $user->github_id = $providerId;
        } elseif ($provider === 'telegram') {
            $user->telegram_id = $providerId;
        }
        $user->save();

        return true;
    }

    /**
     * Відв'язує OAuth акаунт від користувача.
     *
     * @param User $user Користувач
     * @param string $provider Провайдер (github, telegram)
     * @return bool Успішність операції
     */
    public static function unlinkOAuth(User $user, string $provider): bool {
        if ($provider === 'github') {
            $user->github_id = null;
        } elseif ($provider === 'telegram') {
            $user->telegram_id = null;
        }
        $user->save();
        return true;
    }

    /**
     * Авторизує користувача через Passkey (WebAuthn).
     *
     * @param string $credentialId ID ключа
     * @return User Авторизований користувач
     * @throws Exception Якщо ключ не знайдено
     */
    public static function passkeyLogin(string $credentialId): User {
        $passkey = Passkey::findByCredentialId($credentialId);
        if (!$passkey) {
            throw new Exception("Passkey не знайдено.");
        }

        $user = User::findById($passkey->user_id);
        if (!$user) {
            throw new Exception("Користувача для цього Passkey не знайдено.");
        }

        self::setSession($user);
        return $user;
    }

    /**
     * Реєструє новий Passkey для користувача.
     *
     * @param string $userId ID користувача
     * @param array $credentialData Дані ключа від verify_attestation_response
     * @return Passkey Створений Passkey
     * @throws Exception При помилці
     */
    public static function registerPasskey(string $userId, array $credentialData): Passkey {
        if (Passkey::countByUserId($userId) >= 5) {
            throw new Exception("Максимально дозволено 5 ключів.");
        }

        $passkey = new Passkey(
            $userId,
            $credentialData['credential_id'],
            $credentialData['public_key_pem'],
            $credentialData['aaguid'] ?? 'unknown',
            $credentialData['transports'] ?? [],
            $credentialData['counter'] ?? 0
        );
        $passkey->save();

        return $passkey;
    }

    /**
     * Генерує новий API-ключ для користувача.
     *
     * @param User $user Користувач
     * @return string Новий API-ключ
     */
    public static function generateApiKey(User $user): string {
        $user->api_key = 'pp_' . bin2hex(random_bytes(24));
        $user->save();
        return $user->api_key;
    }

    /**
     * Оновлює профіль користувача з валідацією.
     *
     * @param User $user Користувач
     * @param string $nickname Новий нікнейм
     * @param string|null $password Новий пароль (null = не змінювати)
     * @param string|null $confirm Підтвердження пароля
     * @param string|null $newEmail Новий email (null = не змінювати)
     * @param string|null $currentPassword Поточний пароль (обов'язковий при зміні пароля)
     * @return array Результат ['email_changed' => bool]
     * @throws Exception При помилці валідації
     */
    public static function updateProfile(User $user, string $nickname, ?string $password = null, ?string $confirm = null, ?string $newEmail = null, ?string $currentPassword = null): array {
        $nickname = htmlspecialchars(trim($nickname));

        if (empty($nickname)) {
            throw new Exception("Нікнейм не може бути порожнім!");
        }

        if (mb_strlen($nickname) > 50) {
            throw new Exception("Нікнейм занадто довгий!");
        }

        $user->nickname = $nickname;

        // Зміна пароля — вимагає підтвердження поточного пароля
        if (!empty($password)) {
            if (empty($currentPassword)) {
                throw new Exception("Введіть поточний пароль для зміни пароля!");
            }
            if (!password_verify($currentPassword, $user->password_hash)) {
                throw new Exception("Невірний поточний пароль!");
            }
            if (mb_strlen($password) < 6) {
                throw new Exception("Пароль має містити мінімум 6 символів!");
            }
            if ($password !== $confirm) {
                throw new Exception("Паролі не співпадають!");
            }
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Регенерація сесії після зміни пароля — інвалідує інші сесії,
            // що спираються на той самий session_id
            session_regenerate_id(true);

            // Анулювання remember_me cookie — користувач має повторно увійти
            // на інших пристроях зі старим паролем
            self::clearRememberCookie();
        }

        $emailChanged = false;

        // Зміна email
        if (!empty($newEmail) && $newEmail !== $user->email) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Некоректний формат нового email!");
            }
            if (User::findByEmail($newEmail)) {
                throw new Exception("Цей email вже зайнятий!");
            }
            $user->email = $newEmail;
            $user->email_verified = 0;
            $emailChanged = true;
        }

        $user->save();

        // Оновлення сесійного кешу
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $user->id) {
            $cacheUser = clone $user;
            $cacheUser->password_hash = null;
            $_SESSION['_user_cache'] = $cacheUser;
        }

        return ['email_changed' => $emailChanged];
    }

    /**
     * Повністю видаляє акаунт користувача та всі пов'язані дані.
     *
     * @param User $user Користувач для видалення
     * @return bool Успішність операції
     */
    public static function deleteAccount(User $user): bool {
        Passkey::deleteByUserId($user->id);
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user->id]);
        self::logout();
        return true;
    }

    /**
     * Перевіряє, чи є поточний користувач адміністратором.
     *
     * @param User|null $user Користувач
     * @return bool
     */
    public static function isAdmin(?User $user): bool {
        return $user !== null && $user->role === 'admin';
    }

    /**
     * Перевіряє, чи авторизований користувач має доступ до ресурсу як автор або адмін.
     *
     * @param User|null $user Користувач
     * @param string|null $resourceOwnerId ID власника ресурсу
     * @return bool
     */
    public static function isOwnerOrAdmin(?User $user, ?string $resourceOwnerId): bool {
        if (!$user) return false;
        return $user->id === $resourceOwnerId || $user->role === 'admin';
    }

    /**
     * Верифікує email користувача за кодом.
     *
     * @param User $user Користувач
     * @param string $code Код підтвердження
     * @return bool Успішність верифікації
     * @throws Exception При невірному або протермінованому коді
     */
    public static function verifyEmail(User $user, string $code): bool {
        if ($user->email_verified) return true;
        if (empty($user->verification_code) || empty($user->verification_expires_at)) {
            throw new Exception("Код підтвердження відсутній.");
        }

        if (strtotime($user->verification_expires_at) < time()) {
            throw new Exception("Код протермінований.");
        }

        if ($user->verification_code !== $code) {
            throw new Exception("Невірний код підтвердження.");
        }

        $user->email_verified = 1;
        $user->verification_code = null;
        $user->verification_expires_at = null;
        $user->save();

        return true;
    }

    /**
     * Генерує новий код підтвердження пошти для користувача.
     *
     * @param User $user Користувач
     * @return string Згенерований 6-значний код
     */
    public static function generateVerificationCode(User $user): string {
        $user->verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->verification_expires_at = date('Y-m-d H:i:s', time() + 15 * 60); // 15 хвилин
        $user->email_verified = 0;
        $user->save();
        return $user->verification_code;
    }

    /**
     * Реєструє нового користувача для Passkey-авторизації (без звичайного пароля).
     *
     * @param string $email Email
     * @param string $password Тимчасовий пароль
     * @param string $nickname Нікнейм
     * @return User Створений користувач
     */
    public static function registerPasskeyUser(string $email, string $password, string $nickname): User {
        $user = new User($email, password_hash($password, PASSWORD_DEFAULT), $nickname, 100);
        $user->save();
        return $user;
    }

    /**
     * Видаляє користувача адміністратором.
     *
     * @param string $userId ID користувача
     * @return bool Успішність операції
     */
    public static function deleteByAdmin(string $userId): bool {
        $user = User::findById($userId);
        if (!$user) {
            return false;
        }
        Passkey::deleteByUserId($userId);
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return true;
    }
}
