<?php
require_once __DIR__ . '/../../config/db.php';

/**
 * Модель для роботи з фінансовими транзакціями (витрати та поповнення).
 */
class Transaction {
    public $id;
    public $user_id;
    public $amount;
    public $type;
    public $service;
    public $related_paste_id;
    public $related_order_id;
    public $description;
    public $created_at;

    /**
     * Конструктор транзакції.
     */
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null; // ID може бути null для нової вставки
        $this->user_id = $data['user_id'] ?? null;
        $this->amount = $data['amount'] ?? 0;
        $this->type = $data['type'] ?? 'topup';
        $this->service = $data['service'] ?? null;
        $this->related_paste_id = $data['related_paste_id'] ?? null;
        $this->related_order_id = $data['related_order_id'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
    }

    /**
     * Збереження транзакції в базу даних.
     */
    public function save() {
        $pdo = DB::getInstance()->getPDO();
        if ($this->id !== null && is_numeric($this->id)) {
            $stmt = $pdo->prepare("
                UPDATE transactions SET
                    user_id = ?, amount = ?, type = ?, service = ?, related_paste_id = ?, related_order_id = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([$this->user_id, $this->amount, $this->type, $this->service, $this->related_paste_id, $this->related_order_id, $this->description, $this->id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, amount, type, service, related_paste_id, related_order_id, description, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->user_id, $this->amount, $this->type, $this->service, $this->related_paste_id, $this->related_order_id, $this->description, $this->created_at]);
            $this->id = $pdo->lastInsertId();
        }
    }

    /**
     * Підрахунок загальної кількості транзакцій.
     */
    public static function countAll(string $type = ''): int {
        return self::count($type);
    }

    /**
     * Отримання всіх транзакцій з JOIN по користувачах (з пагінацією).
     */
    public static function getAll(int $limit = 50, int $offset = 0, string $type = ''): array {
        $pdo = DB::getInstance()->getPDO();
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
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Сума всіх поповнень (topup) — реальний заробіток системи.
     */
    public static function sumTopups(): int {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = ? AND amount > 0');
        $stmt->execute(['topup']);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Підрахунок транзакцій за допомогою окремого запиту (без помилок).
     */
    public static function count(string $type = ''): int {
        $pdo = DB::getInstance()->getPDO();
        if ($type !== '') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE type = ?');
            $stmt->execute([$type]);
        } else {
            $stmt = $pdo->query('SELECT COUNT(*) FROM transactions');
        }
        return (int) $stmt->fetchColumn();
    }
}
