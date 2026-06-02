<?php
/**
 * Клас Transaction — Доменна модель фінансової транзакції.
 *
 * Містить лише дані.
 * Персистентність винесено в TransactionRepository.
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
    public $idempotency_key;
    public $created_at;

    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
        $this->amount = $data['amount'] ?? 0;
        $this->type = $data['type'] ?? 'topup';
        $this->service = $data['service'] ?? null;
        $this->related_paste_id = $data['related_paste_id'] ?? null;
        $this->related_order_id = $data['related_order_id'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->idempotency_key = $data['idempotency_key'] ?? null;
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
    }

    // ── Персистентність (делегує) ──

    /** @deprecated Використовуйте Repo::transactions()->save($tx) */
    public function save(): void {
        Repo::transactions()->save($this);
    }

    // ── Статичні методи для зворотної сумісності ──

    /** @deprecated */
    public static function create($user_id, $amount, $type, $service = null, $paste_id = null, $order_id = null, $description = null): self {
        return Repo::transactions()->create($user_id, $amount, $type, $service, $paste_id, $order_id, $description);
    }

    /** @deprecated */
    public static function getAll(int $limit = 50, int $offset = 0, string $type = ''): array {
        return Repo::transactions()->getAll($limit, $offset, $type);
    }

    /** @deprecated */
    public static function count(string $type = ''): int {
        return Repo::transactions()->count($type);
    }

    /** @deprecated */
    public static function countAll(string $type = ''): int {
        return Repo::transactions()->countAll($type);
    }

    /** @deprecated */
    public static function sumTopups(): int {
        return Repo::transactions()->sumTopups();
    }
}
