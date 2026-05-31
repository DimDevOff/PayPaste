<?php

require_once __DIR__ . '/../../config/db.php';

/**
 * TransactionManager — static helper that wraps PDO transactions.
 *
 * Provides a single `run()` method that begins a transaction, executes
 * the given callable receiving the PDO instance, commits on success,
 * and rolls back on any exception.
 *
 * Also provides shared helpers used by Command classes:
 *   - checkIdempotency() — idempotency-key guard
 *   - syncSessionCache()  — updates the in-session User cache
 *   - defaultDescription() — maps transaction types to Ukrainian labels
 */
class TransactionManager
{
    /**
     * Execute a callable inside a PDO transaction.
     *
     * @param callable $fn  Receives PDO instance, must return the result.
     * @return mixed        Whatever $fn returns.
     * @throws Exception    On any failure — the transaction is rolled back.
     */
    public static function run(callable $fn): mixed
    {
        $pdo = DB::getInstance()->getPDO();

        if ($pdo->inTransaction()) {
            // Already inside a transaction — don't nest, just execute.
            return $fn($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Check whether an operation with the given idempotency key has already
     * been executed.
     *
     * @return array|null  The existing transaction row, or null if this key is new.
     */
    public static function checkIdempotency(\PDO $pdo, ?string $idempotencyKey): ?array
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT id, amount, type FROM transactions WHERE idempotency_key = ? LIMIT 1"
        );
        $stmt->execute([$idempotencyKey]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $existing ?: null;
    }

    /**
     * Synchronise the in-session User cache with the current User object.
     */
    public static function syncSessionCache(\User $user): void
    {
        if (
            isset($_SESSION['user_id'], $_SESSION['_user_cache'])
            && $_SESSION['user_id'] === $user->id
        ) {
            $_SESSION['_user_cache']->credits = $user->credits;
            if (property_exists($user, 'unlocked_pastes')) {
                $_SESSION['_user_cache']->unlocked_pastes = $user->unlocked_pastes;
            }
        }
    }

    /**
     * Return a human-readable Ukrainian description for a transaction type.
     */
    public static function defaultDescription(string $type): string
    {
        $map = [
            'topup'        => 'Поповнення балансу',
            'creation_fee' => 'Плата за створення платної пасти',
            'purchase'     => 'Купівля доступу до пасти',
            'sale'         => 'Продаж доступу до пасти',
            'api_usage'    => 'Використання API',
            'ad_reward'    => 'Нагорода за перегляд реклами',
        ];
        return $map[$type] ?? 'Фінансова операція';
    }
}
