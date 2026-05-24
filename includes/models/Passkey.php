<?php
require_once __DIR__ . '/../../config/db.php';

/**
 * Модель для роботи з Passkeys (WebAuthn обліковими даними).
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

    /**
     * Конструктор Passkey.
     */
    public function __construct($user_id, $credential_id, $public_key_pem, $aaguid = null, $transports = [], $counter = 0, $id = null, $created_at = null) {
        $this->user_id = $user_id;
        $this->credential_id = $credential_id;
        $this->public_key_pem = $public_key_pem;
        $this->aaguid = $aaguid;
        $this->transports = is_array($transports) ? json_encode($transports) : $transports;
        $this->counter = $counter;
        $this->id = $id ?? uniqid('pk_');
        $this->created_at = $created_at ?? date('Y-m-d H:i:s');
    }

    /**
     * Отримання масиву способів передачі (NFC, USB і т.д.).
     */
    public function getTransportsArray() {
        if (empty($this->transports)) return [];
        $decoded = json_decode($this->transports, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Збереження Passkey в базу даних.
     */
    public function save() {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO passkeys (id, user_id, credential_id, public_key_pem, counter, aaguid, transports, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id = ?,
                credential_id = ?,
                public_key_pem = ?,
                counter = ?,
                aaguid = ?,
                transports = ?
        ");
        $stmt->execute([
            $this->id,
            $this->user_id,
            $this->credential_id,
            $this->public_key_pem,
            $this->counter,
            $this->aaguid,
            $this->transports,
            $this->created_at,
            // Значення для ON DUPLICATE KEY UPDATE (використовуємо prepared statements для сумісності з MariaDB 10.4)
            $this->user_id,
            $this->credential_id,
            $this->public_key_pem,
            $this->counter,
            $this->aaguid,
            $this->transports
        ]);
    }

    /**
     * Пошук Passkey за Credential ID.
     */
    public static function findByCredentialId($credential_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM passkeys WHERE credential_id = ?");
        $stmt->execute([$credential_id]);
        $row = $stmt->fetch();
        if ($row) {
            return new self($row['user_id'], $row['credential_id'], $row['public_key_pem'], $row['aaguid'], $row['transports'], $row['counter'], $row['id'], $row['created_at']);
        }
        return null;
    }

    /**
     * Пошук всіх Passkeys користувача.
     */
    public static function findByUserId($user_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM passkeys WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = new self($row['user_id'], $row['credential_id'], $row['public_key_pem'], $row['aaguid'], $row['transports'], $row['counter'], $row['id'], $row['created_at']);
        }
        return $result;
    }

    /**
     * Підрахунок кількості Passkeys у користувача.
     */
    public static function countByUserId($user_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM passkeys WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    /**
     * Оновлення лічильника використань.
     */
    public function updateCounter($new_counter) {
        $this->counter = $new_counter;
        $this->save();
    }

    /**
     * Видалення конкретного Passkey.
     */
    public static function deleteById($id, $user_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("DELETE FROM passkeys WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }

    /**
     * Видалення всіх Passkeys користувача.
     */
    public static function deleteByUserId($user_id) {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("DELETE FROM passkeys WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
}
