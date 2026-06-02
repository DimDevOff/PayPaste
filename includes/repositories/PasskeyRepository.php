<?php
require_once __DIR__ . '/../models/Passkey.php';

/**
 * Репозиторій для роботи з Passkeys (WebAuthn).
 */
class PasskeyRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findByCredentialId(string $credential_id): ?Passkey {
        $stmt = $this->pdo->prepare("SELECT * FROM passkeys WHERE credential_id = ?");
        $stmt->execute([$credential_id]);
        $row = $stmt->fetch();
        if ($row) {
            return new Passkey(
                $row['user_id'],
                $row['credential_id'],
                $row['public_key_pem'],
                $row['aaguid'],
                $row['transports'],
                $row['counter'],
                $row['id'],
                $row['created_at']
            );
        }
        return null;
    }

    /**
     * @return Passkey[]
     */
    public function findByUserId(string $user_id): array {
        $stmt = $this->pdo->prepare("SELECT * FROM passkeys WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = new Passkey(
                $row['user_id'],
                $row['credential_id'],
                $row['public_key_pem'],
                $row['aaguid'],
                $row['transports'],
                $row['counter'],
                $row['id'],
                $row['created_at']
            );
        }
        return $result;
    }

    public function countByUserId(string $user_id): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM passkeys WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    public function save(Passkey $passkey): void {
        $stmt = $this->pdo->prepare("
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
            $passkey->id,
            $passkey->user_id,
            $passkey->credential_id,
            $passkey->public_key_pem,
            $passkey->counter,
            $passkey->aaguid,
            $passkey->transports,
            $passkey->created_at,
            // ON DUPLICATE KEY UPDATE values
            $passkey->user_id,
            $passkey->credential_id,
            $passkey->public_key_pem,
            $passkey->counter,
            $passkey->aaguid,
            $passkey->transports
        ]);
    }

    public function deleteById(string $id, string $user_id): void {
        $stmt = $this->pdo->prepare("DELETE FROM passkeys WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }

    public function deleteByUserId(string $user_id): void {
        $stmt = $this->pdo->prepare("DELETE FROM passkeys WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
}
