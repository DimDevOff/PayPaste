<?php
/**
 * Клас User — Доменна модель користувача.
 *
 * Містить лише дані та бізнес-логіку.
 * Персистентність (SQL) винесено в UserRepository.
 *
 * Для зворотної сумісності статичні методи делегують до Repo::users().
 * Новий код має використовувати Repo::users()->findById($id) напряму.
 */
class User {
    public $id;
    public $email;
    public $telegram_id;
    public $github_id;
    public $password_hash;
    public $nickname;
    public $credits;
    public $unlocked_pastes;
    public $role;
    public $theme;
    public $api_key;
    public $email_verified;
    public $verification_code;
    public $verification_expires_at;

    public function __construct(
        $email,
        $password_hash,
        $nickname = 'Anon',
        $credits = 100,
        $unlocked_pastes = [],
        $role = 'user',
        $id = null,
        $telegram_id = null,
        $github_id = null,
        $theme = 'retro',
        $api_key = null,
        $email_verified = 0,
        $verification_code = null,
        $verification_expires_at = null
    ) {
        $this->email = $email;
        $this->password_hash = $password_hash;
        $this->nickname = trim($nickname);
        $this->credits = $credits;
        $this->unlocked_pastes = $unlocked_pastes;
        $this->role = $role;
        $this->id = $id ?? 'u_' . bin2hex(random_bytes(8));
        $this->telegram_id = $telegram_id;
        $this->github_id = $github_id;
        $this->theme = $theme;
        $this->api_key = $api_key;
        $this->email_verified = (int)$email_verified;
        $this->verification_code = $verification_code;
        $this->verification_expires_at = $verification_expires_at;
    }

    // ── Бізнес-логіка ──

    /** Перевіряє, чи має користувач доступ до конкретної пасти. */
    public function hasUnlocked(string $paste_id): bool {
        return in_array($paste_id, $this->unlocked_pastes);
    }

    // ── Персистентність (делегує до Repository) ──

    /** @deprecated Використовуйте Repo::users()->save($user) */
    public function save(): void {
        Repo::users()->save($this);
    }

    // ── Статичні методи для зворотної сумісності ──
    // @deprecated Використовуйте Repo::users() напряму.

    /** @deprecated */
    public static function findById($id): ?self {
        return Repo::users()->findById($id);
    }

    /** @deprecated */
    public static function findByEmail($email): ?self {
        return Repo::users()->findByEmail($email);
    }

    /** @deprecated */
    public static function findByTelegramId($telegram_id): ?self {
        return Repo::users()->findByTelegramId($telegram_id);
    }

    /** @deprecated */
    public static function findByGithubId($github_id): ?self {
        return Repo::users()->findByGithubId($github_id);
    }

    /** @deprecated */
    public static function findByApiKey($api_key): ?self {
        return Repo::users()->findByApiKey($api_key);
    }

    /** @deprecated */
    public static function countAll($search = ''): int {
        return Repo::users()->countAll($search);
    }

    /** @deprecated */
    public static function getAll($limit = 25, $offset = 0, $search = ''): array {
        return Repo::users()->getAll($limit, $offset, $search);
    }
}
