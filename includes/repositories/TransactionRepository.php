<?php
require_once __DIR__ . '/../models/Transaction.php';

/**
 * Репозиторій для роботи з транзакціями.
 */
class TransactionRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Збереження транзакції (INSERT або UPDATE).
     */
    public function save(Transaction $tx): void {
        if ($tx->id !== null && is_numeric($tx->id)) {
            $stmt = $this->pdo->prepare("
                UPDATE transactions SET
                    user_id = ?, amount = ?, type = ?, service = ?,
                    related_paste_id = ?, related_order_id = ?, description = ?, idempotency_key = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $tx->user_id, $tx->amount, $tx->type, $tx->service,
                $tx->related_paste_id, $tx->related_order_id, $tx->description,
                $tx->idempotency_key, $tx->id
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions (user_id, amount, type, service, related_paste_id, related_order_id, description, idempotency_key, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tx->user_id, $tx->amount, $tx->type, $tx->service,
                $tx->related_paste_id, $tx->related_order_id, $tx->description,
                $tx->idempotency_key, $tx->created_at
            ]);
            $tx->id = $this->pdo->lastInsertId();
        }
    }

    /**
     * Швидке створення транзакції (фабричний метод).
     */
    public function create(
        $user_id,
        $amount,
        $type,
        $service = null,
        $paste_id = null,
        $order_id = null,
        $description = null
    ): Transaction {
        $t = new Transaction([
            'user_id' => $user_id,
            'amount' => $amount,
            'type' => $type,
            'service' => $service,
            'related_paste_id' => $paste_id,
            'related_order_id' => $order_id,
            'description' => $description
        ]);
        $this->save($t);
        return $t;
    }

    // ── Агрегатні / адмін ──

    /**
     * @return array rows from DB with joined user data
     */
    public function getAll(int $limit = 50, int $offset = 0, string $type = ''): array {
        $sql = '
            SELECT t.*, u.nickname, u.email
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.id
        ';
        $params = [];
        if ($type !== '') {
            $sql .= ' WHERE t.type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(string $type = ''): int {
        if ($type !== '') {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM transactions WHERE type = ?');
            $stmt->execute([$type]);
        } else {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM transactions');
        }
        return (int) $stmt->fetchColumn();
    }

    public function countAll(string $type = ''): int {
        return $this->count($type);
    }

    public function sumTopups(): int {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = ? AND amount > 0');
        $stmt->execute(['topup']);
        return (int) $stmt->fetchColumn();
    }
}
