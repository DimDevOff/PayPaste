<?php
require_once __DIR__ . '/../models/User.php';

/**
 * Репозиторій для роботи з користувачами.
 * Відокремлює SQL-запити від бізнес-логіки моделі User.
 */
class UserRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ── Factory: створення об'єкта User з рядка БД ──

    private function loadUnlockedPastes(string $user_id): array {
        $stmt = $this->pdo->prepare("SELECT paste_id FROM unlocked_pastes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Створює об'єкт User з рядка БД.
     */
    public function instantiateFromRow(?array $row): ?User {
        if (!$row) return null;

        $unlocked = $this->loadUnlockedPastes($row['id']);

        $theme = $row['theme'] ?? 'retro';
        $allowed = ['retro', 'dark', 'terminal', 'light', 'github', 'retro-green'];
        if (!in_array($theme, $allowed)) {
            $theme = 'retro';
        }

        return new User(
            $row['email'],
            $row['password_hash'],
            $row['nickname'],
            $row['credits'],
            $unlocked,
            $row['role'],
            $row['id'],
            $row['telegram_id'],
            $row['github_id'],
            $theme,
            $row['api_key'] ?? null,
            $row['email_verified'] ?? 0,
            $row['verification_code'] ?? null,
            $row['verification_expires_at'] ?? null
        );
    }

    // ── Пошук ──

    public function findById(string $id): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $this->instantiateFromRow($stmt->fetch() ?: null);
    }

    public function findByEmail(string $email): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $this->instantiateFromRow($stmt->fetch() ?: null);
    }

    public function findByTelegramId(string $telegram_id): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegram_id]);
        return $this->instantiateFromRow($stmt->fetch() ?: null);
    }

    public function findByGithubId(string $github_id): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE github_id = ?");
        $stmt->execute([$github_id]);
        return $this->instantiateFromRow($stmt->fetch() ?: null);
    }

    public function findByApiKey(string $api_key): ?User {
        if (!$api_key) return null;
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE api_key = ?");
        $stmt->execute([$api_key]);
        return $this->instantiateFromRow($stmt->fetch() ?: null);
    }

    // ── Збереження ──

    /**
     * Зберігає або оновлює користувача в БД.
     */
    public function save(User $user): void {
        // Якщо хеш порожній, дістаємо з БД
        if (empty($user->password_hash)) {
            $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user->id]);
            $hash = $stmt->fetchColumn();
            if ($hash) {
                $user->password_hash = $hash;
            }
        }

        $stmt = $this->pdo->prepare("
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
            $user->id,
            $user->email,
            $user->telegram_id,
            $user->github_id,
            $user->password_hash,
            $user->nickname,
            $user->credits,
            $user->role,
            $user->theme,
            $user->api_key,
            $user->email_verified,
            $user->verification_code,
            $user->verification_expires_at
        ]);

        // Оновлюємо розблоковані пасти
        $currentUnlocked = $this->loadUnlockedPastes($user->id);
        $toInsert = array_diff($user->unlocked_pastes, $currentUnlocked);
        if (!empty($toInsert)) {
            $insertStmt = $this->pdo->prepare("INSERT IGNORE INTO unlocked_pastes (user_id, paste_id) VALUES (?, ?)");
            foreach ($toInsert as $pasteId) {
                $insertStmt->execute([$user->id, $pasteId]);
            }
        }

        // Оновлюємо кеш сесії, якщо це поточний користувач
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === $user->id) {
            $cacheUser = clone $user;
            $cacheUser->password_hash = null;
            $_SESSION['_user_cache'] = $cacheUser;
        }
    }

    // ── Збірні запити ──

    public function countAll(string $search = ''): int {
        if ($search !== '') {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email LIKE ? OR nickname LIKE ?");
            $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
            return $stmt->fetchColumn();
        }
        return $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    /**
     * @return array rows from DB (not User objects, for admin panel)
     */
    public function getAll(int $limit = 25, int $offset = 0, string $search = ''): array {
        $sql = "SELECT * FROM users";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE email LIKE :search_email OR nickname LIKE :search_nickname";
            $params[':search_email'] = '%' . $search . '%';
            $params[':search_nickname'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
