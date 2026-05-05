<?php
require_once __DIR__ . '/../../config/db.php';

/**
 * Клас User — Модель для роботи з користувачами
 * Відповідає за авторизацію, профілі, баланс кредитів та доступ до паст
 */
class User {
    public $id;               // Унікальний ID користувача (формат u_...)
    public $email;            // Електронна пошта
    public $telegram_id;      // ID Telegram (для OAuth та сповіщень)
    public $github_id;        // ID GitHub (для OAuth)
    public $password_hash;    // Хеш пароля
    public $nickname;         // Нікнейм користувача
    public $credits;          // Поточний баланс кредитів
    public $unlocked_pastes;  // Масив ID паст, до яких користувач купив доступ
    public $role;             // Роль користувача (user, admin)
    public $theme;            // Обрана кольорова тема інтерфейсу
    public $api_key;          // Ключ для доступу до API
    public $email_verified;   // Статус верифікації
    public $verification_code; // Код
    public $verification_expires_at; // Час життя коду

    /**
     * Конструктор моделі користувача
     */
    public function __construct($email, $password_hash, $nickname = 'Anon', $credits = 100, $unlocked_pastes = [], $role = 'user', $id = null, $telegram_id = null, $github_id = null, $theme = 'retro', $api_key = null, $email_verified = 0, $verification_code = null, $verification_expires_at = null) {
        $this->email = $email;
        $this->password_hash = $password_hash;
        $this->nickname = trim($nickname);
        $this->credits = $credits;
        $this->unlocked_pastes = $unlocked_pastes;
        $this->role = $role;
        $this->id = $id ?? uniqid('u_');
        $this->telegram_id = $telegram_id;
        $this->github_id = $github_id;
        $this->theme = $theme;
        $this->api_key = $api_key;
        $this->email_verified = (int)$email_verified;
        $this->verification_code = $verification_code;
        $this->verification_expires_at = $verification_expires_at;
    }

    /**
     * Завантажує список розблокованих паст для користувача з БД
     */
    private static function loadUnlockedPastes($user_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT paste_id FROM unlocked_pastes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Створює екземпляр User з рядка бази даних
     */
    private static function instantiateFromRow($row) {
        if (!$row) return null;
        $unlocked = self::loadUnlockedPastes($row['id']);
        
        $theme = $row['theme'] ?? 'retro';
        $allowed = ['retro', 'dark', 'terminal', 'light', 'github-orange', 'retro-green'];
        if (!in_array($theme, $allowed)) {
            $theme = 'retro';
        }
        
        return new self($row['email'], $row['password_hash'], $row['nickname'], $row['credits'], $unlocked, $row['role'], $row['id'], $row['telegram_id'], $row['github_id'], $theme, $row['api_key'] ?? null, $row['email_verified'] ?? 0, $row['verification_code'] ?? null, $row['verification_expires_at'] ?? null);
    }

    /**
     * Зберігає або оновлює дані користувача в БД
     */
    public function save() {
        $pdo = DB::getInstance()->getPDO();
        
        // Якщо хеш порожній (наприклад, об'єкт із кешу сесії), дістаємо його з БД
        if (empty($this->password_hash)) {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$this->id]);
            $hash = $stmt->fetchColumn();
            if ($hash) {
                $this->password_hash = $hash;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (id, email, telegram_id, github_id, password_hash, nickname, credits, role, theme, api_key, email_verified, verification_code, verification_expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                telegram_id = VALUES(telegram_id),
                github_id = VALUES(github_id),
                password_hash = IF(VALUES(password_hash) IS NOT NULL AND VALUES(password_hash) != '', VALUES(password_hash), password_hash),
                nickname = VALUES(nickname),
                credits = VALUES(credits),
                role = VALUES(role),
                theme = VALUES(theme),
                api_key = VALUES(api_key),
                email_verified = VALUES(email_verified),
                verification_code = VALUES(verification_code),
                verification_expires_at = VALUES(verification_expires_at)
        ");
        $stmt->execute([
            $this->id,
            $this->email,
            $this->telegram_id,
            $this->github_id,
            $this->password_hash,
            $this->nickname,
            $this->credits,
            $this->role,
            $this->theme,
            $this->api_key,
            $this->email_verified,
            $this->verification_code,
            $this->verification_expires_at
        ]);
        
        // Оновлюємо розблоковані пасти
        $currentUnlocked = self::loadUnlockedPastes($this->id);
        $toInsert = array_diff($this->unlocked_pastes, $currentUnlocked);
        if (!empty($toInsert)) {
            $insertStmt = $pdo->prepare("INSERT IGNORE INTO unlocked_pastes (user_id, paste_id) VALUES (?, ?)");
            foreach ($toInsert as $pasteId) {
                $insertStmt->execute([$this->id, $pasteId]);
            }
        }
        
        // Оновлюємо кеш сесії, якщо це поточний користувач
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $this->id) {
            $cacheUser = clone $this;
            $cacheUser->password_hash = null; // Видаляємо хеш перед кешуванням в сесію
            $_SESSION['_user_cache'] = $cacheUser;
        }
    }

    /**
     * Пошук користувача за email
     */
    public static function findByEmail($email) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return self::instantiateFromRow($stmt->fetch());
    }
    
    /**
     * Пошук користувача за внутрішнім ID
     */
    public static function findById($id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return self::instantiateFromRow($stmt->fetch());
    }

    /**
     * Пошук користувача за Telegram ID
     */
    public static function findByTelegramId($telegram_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegram_id]);
        return self::instantiateFromRow($stmt->fetch());
    }

    /**
     * Прив'язка Telegram аккаунта до профілю
     */
    public function linkTelegram($telegram_id) {
        $this->telegram_id = $telegram_id;
        $this->save();
    }

    /**
     * Пошук користувача за GitHub ID
     */
    public static function findByGithubId($github_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE github_id = ?");
        $stmt->execute([$github_id]);
        return self::instantiateFromRow($stmt->fetch());
    }

    /**
     * Прив'язка GitHub аккаунта до профілю
     */
    public function linkGithub($github_id) {
        $this->github_id = $github_id;
        $this->save();
    }

    /**
     * Повне видалення користувача та його Passkey-ключів
     */
    public function delete() {
        require_once __DIR__ . '/Passkey.php';
        Passkey::deleteByUserId($this->id);
        
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$this->id]);
    }

    /**
     * Встановлює нову тему для користувача та зберігає в БД
     */
    public function setTheme($theme) {
        $allowed = ['retro', 'dark', 'terminal', 'light', 'github-orange', 'retro-green'];
        if (!in_array($theme, $allowed)) {
            $theme = 'retro';
        }
        $this->theme = $theme;
        $this->save();
    }

    /**
     * Перевірка, чи має користувач доступ до конкретної пасти
     */
    public function hasUnlocked($paste_id) {
        return in_array($paste_id, $this->unlocked_pastes);
    }
    
    /**
     * Додає пасту до списку розблокованих
     */
    public function unlockPaste($paste_id) {
        if (!in_array($paste_id, $this->unlocked_pastes)) {
            $this->unlocked_pastes[] = $paste_id;
            $this->save();
        }
    }

    /**
     * Підрахунок загальної кількості користувачів (для адмін-панелі)
     */
    public static function countAll($search = '') {
        $pdo = DB::getInstance()->getPDO();
        if ($search !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email LIKE ? OR nickname LIKE ?");
            $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
            return $stmt->fetchColumn();
        }
        return $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    /**
     * Отримання списку всіх користувачів з підтримкою пагінації.
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAll($limit = 25, $offset = 0, $search = '') {
        $pdo = DB::getInstance()->getPDO();
        $sql = "SELECT * FROM users";
        $params = [];
        
        if ($search !== '') {
            $sql .= " WHERE email LIKE :search_email OR nickname LIKE :search_nickname";
            $params[':search_email'] = '%' . $search . '%';
            $params[':search_nickname'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Видалення користувача адміністратором
     */
    public static function delete_by_admin($id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Генерує новий API-ключ для користувача
     */
    public function generateApiKey() {
        $this->api_key = 'pp_' . bin2hex(random_bytes(24));
        $this->save();
        return $this->api_key;
    }

    /**
     * Пошук користувача за API-ключем
     */
    public static function findByApiKey($api_key) {
        if (!$api_key) return null;
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ?");
        $stmt->execute([$api_key]);
        return self::instantiateFromRow($stmt->fetch());
    }
    /**
     * Оновлює критичні дані користувача прямо з бази даних (баланс, розблоковані пасти)
     * Це потрібно для підтримки актуальності кешу сесії.
     */
    public function refreshData() {
        $pdo = DB::getInstance()->getPDO();
        
        // Оновлюємо баланс
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$this->id]);
        $res = $stmt->fetchColumn();
        if ($res !== false) {
            $this->credits = (int)$res;
        }
        
        // Оновлюємо список розблокованих паст
        $this->unlocked_pastes = self::loadUnlockedPastes($this->id);

        // Синхронізуємо з сесією
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $this->id) {
            $cacheUser = clone $this;
            $cacheUser->password_hash = null;
            $_SESSION['_user_cache'] = $cacheUser;
        }
    }

    /**
     * Генерує новий код підтвердження пошти
     */
    public function generateVerificationCode() {
        $this->verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->verification_expires_at = date('Y-m-d H:i:s', time() + 15 * 60); // 15 хвилин
        $this->email_verified = 0;
        $this->save();
        return $this->verification_code;
    }

    /**
     * Перевірка коду підтвердження пошти
     */
    public function verifyEmail($code) {
        if ($this->email_verified) return true;
        if (empty($this->verification_code) || empty($this->verification_expires_at)) return false;
        
        if (strtotime($this->verification_expires_at) < time()) {
            return false; // протерміновано
        }
        
        if ($this->verification_code === $code) {
            $this->email_verified = 1;
            $this->verification_code = null;
            $this->verification_expires_at = null;
            $this->save();
            return true;
        }
        
        return false;
    }
}
