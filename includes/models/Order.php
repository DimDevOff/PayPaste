<?php
/**
 * Клас Order — Доменна модель замовлення (поповнення балансу).
 *
 * Персистентність винесено в OrderRepository.
 */
class Order {
    public $id;
    public $user_id;
    public $service;
    public $amount_credits;
    public $status;
    public $external_provider_id;
    public $created_at;
    public $updated_at;

    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
        $this->service = $data['service'] ?? null;
        $this->amount_credits = $data['amount_credits'] ?? null;
        $this->status = $data['status'] ?? 'pending';
        $this->external_provider_id = $data['external_provider_id'] ?? null;
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->updated_at = $data['updated_at'] ?? date('Y-m-d H:i:s');
    }

    // ── Персистентність (делегує) ──

    /** @deprecated Використовуйте Repo::orders()->save($order) */
    public function save(): void {
        Repo::orders()->save($this);
    }

    // ── Статичні методи для зворотної сумісності ──

    /** @deprecated */
    public static function findById($id): ?self {
        return Repo::orders()->findById($id);
    }
}
