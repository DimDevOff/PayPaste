<?php
require_once __DIR__ . '/../../config/db.php';

/**
 * Клас Order — Модель для роботи з замовленнями (поповненнями балансу)
 * Використовується для відстеження платежів через зовнішні сервіси (Donatello, Telegram Stars)
 */
class Order
{
    public $id;                   // Унікальний ідентифікатор замовлення
    public $user_id;              // ID користувача, який зробив замовлення
    public $service;              // Назва сервісу оплати (напр. 'donatello', 'telegram_stars')
    public $amount_credits;       // Кількість кредитів для нарахування
    public $status;               // Статус замовлення: pending, completed, failed
    public $external_provider_id; // Ідентифікатор транзакції у зовнішній платіжній системі
    public $created_at;           // Дата та час створення замовлення
    public $updated_at;           // Дата та час останнього оновлення

    /**
     * Конструктор моделі замовлення
     * @param array $data Масив з даними
     */
    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
        $this->service = $data['service'] ?? null;
        $this->amount_credits = $data['amount_credits'] ?? null;
        $this->status = $data['status'] ?? 'pending';
        $this->external_provider_id = $data['external_provider_id'] ?? null;
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->updated_at = $data['updated_at'] ?? date('Y-m-d H:i:s');
    }

    /**
     * Зберігає замовлення в базу даних.
     * Якщо замовлення з таким ID вже існує, оновлює його дані (статус, час оновлення тощо).
     */
    public function save()
    {
        $this->updated_at = date('Y-m-d H:i:s');
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
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
            $this->id,
            $this->user_id,
            $this->service,
            $this->amount_credits,
            $this->status,
            $this->external_provider_id,
            $this->created_at,
            $this->updated_at
        ]);
    }

    /**
     * Знаходить замовлення за його ідентифікатором
     * @param string|int $id ID замовлення
     * @return Order|null Повертає об'єкт Order або null, якщо не знайдено
     */
    public static function findById($id)
    {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            return new self($row);
        }
        return null;
    }
}
