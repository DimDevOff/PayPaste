<?php
/**
 * Клас Passkey — Доменна модель WebAuthn облікових даних.
 *
 * Персистентність винесено в PasskeyRepository.
 */
class Passkey {
    public $id;
    public $user_id;
    public $credential_id;
    public $public_key_pem;
    public $counter;
    public $aaguid;
    public $transports;
    public $created_at;

    public function __construct(
        $user_id,
        $credential_id,
        $public_key_pem,
        $aaguid = null,
        $transports = [],
        $counter = 0,
        $id = null,
        $created_at = null
    ) {
        $this->user_id = $user_id;
        $this->credential_id = $credential_id;
        $this->public_key_pem = $public_key_pem;
        $this->aaguid = $aaguid;
        $this->transports = is_array($transports) ? json_encode($transports) : $transports;
        $this->counter = $counter;
        $this->id = $id ?? uniqid('pk_');
        $this->created_at = $created_at ?? date('Y-m-d H:i:s');
    }

    /** Отримання масиву способів передачі. */
    public function getTransportsArray(): array {
        if (empty($this->transports)) return [];
        $decoded = json_decode($this->transports, true);
        return is_array($decoded) ? $decoded : [];
    }

    // ── Персистентність (делегує) ──

    /** @deprecated Використовуйте Repo::passkeys()->save($pk) */
    public function save(): void {
        Repo::passkeys()->save($this);
    }

    /** @deprecated Використовуйте Repo::passkeys()->save() після зміни counter */
    public function updateCounter($new_counter): void {
        $this->counter = $new_counter;
        Repo::passkeys()->save($this);
    }

    // ── Статичні методи для зворотної сумісності ──

    /** @deprecated */
    public static function findByCredentialId($credential_id): ?self {
        return Repo::passkeys()->findByCredentialId($credential_id);
    }

    /** @deprecated */
    public static function findByUserId($user_id): array {
        return Repo::passkeys()->findByUserId($user_id);
    }

    /** @deprecated */
    public static function countByUserId($user_id): int {
        return Repo::passkeys()->countByUserId($user_id);
    }

    /** @deprecated */
    public static function deleteById($id, $user_id): void {
        Repo::passkeys()->deleteById($id, $user_id);
    }

    /** @deprecated */
    public static function deleteByUserId($user_id): void {
        Repo::passkeys()->deleteByUserId($user_id);
    }
}
