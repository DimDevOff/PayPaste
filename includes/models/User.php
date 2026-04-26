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

    /**
     * Конструктор моделі користувача
     */
    public function __construct($email, $password_hash, $nickname = 'Anon', $credits = 100, $unlocked_pastes = [], $role = 'user', $id = null, $telegram_id = null, $github_id = null) {
        $this->email = $email;
        $this->password_hash = $password_hash;
        $this->nickname = trim($nickname);
        $this->credits = $credits;
        $this->unlocked_pastes = $unlocked_pastes;
        $this->role = $role;
        $this->id = $id ?? uniqid('u_');
        $this->telegram_id = $telegram_id;
        $this->github_id = $github_id;
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
        return new self($row['email'], $row['password_hash'], $row['nickname'], $row['credits'], $unlocked, $row['role'], $row['id'], $row['telegram_id'], $row['github_id']);
    }

    /**
     * Зберігає або оновлює дані користувача в БД
     */
    public function save() {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO users (id, email, telegram_id, github_id, password_hash, nickname, credits, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                telegram_id = VALUES(telegram_id),
                github_id = VALUES(github_id),
                password_hash = VALUES(password_hash),
                nickname = VALUES(nickname),
                credits = VALUES(credits),
                role = VALUES(role)
        ");
        $stmt->execute([
            $this->id,
            $this->email,
            $this->telegram_id,
            $this->github_id,
            $this->password_hash,
            $this->nickname,
            $this->credits,
            $this->role
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
    public static function countAll() {
        $pdo = DB::getInstance()->getPDO();
        return $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    /**
     * Отримання списку всіх користувачів з підтримкою пагінації.
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAll($limit = 25, $offset = 0) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM users ORDER BY id DESC LIMIT :limit OFFSET :offset");
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
}
