<?php
require_once __DIR__ . '/../models/Order.php';

/**
 * Репозиторій для роботи з замовленнями.
 */
class OrderRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?Order {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            return new Order($row);
        }
        return null;
    }

    public function save(Order $order): void {
        $order->updated_at = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            INSERT INTO orders (id, user_id, service, amount_credits, status, external_provider_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                service = VALUES(service),
                amount_credits = VALUES(amount_credits),
                status = VALUES(status),
                external_provider_id = VALUES(external_provider_id),
                updated_at = VALUES(updated_at)
        ");
        $stmt->execute([
            $order->id,
            $order->user_id,
            $order->service,
            $order->amount_credits,
            $order->status,
            $order->external_provider_id,
            $order->created_at,
            $order->updated_at
        ]);
    }
}
