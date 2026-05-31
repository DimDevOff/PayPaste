<?php
/**
 * CreditService — тонкий фасад для операцій з кредитами.
 *
 * Уся бізнес-логіка делегована окремим Command-класам:
 *   DeductCreditsCommand   — атомарне списання
 *   CreditCreditsCommand   — атомарне нарахування
 *   TransferCreditsCommand — атомарний P2P-переказ
 *   PurchaseAccessCommand  — атомарна купівля доступу до пасти
 *
 * TransactionManager — спільні PDO-транзакції, ідемпотентність, кеш сесії.
 */

require_once __DIR__ . '/TransactionManager.php';
require_once __DIR__ . '/commands/DeductCreditsCommand.php';
require_once __DIR__ . '/commands/CreditCreditsCommand.php';
require_once __DIR__ . '/commands/TransferCreditsCommand.php';
require_once __DIR__ . '/commands/PurchaseAccessCommand.php';

class CreditService
{
    // ═══════════════════════════════════════════════════════════
    // Прості хелпери (без зовнішніх залежностей)
    // ═══════════════════════════════════════════════════════════

    /**
     * Розраховує вартість створення платної пасти на основі довжини контенту.
     * Формула: ceil(довжина / 10)
     */
    public static function calculateCreationCost(string $content): int
    {
        return (int) ceil(mb_strlen($content) / 10);
    }

    /**
     * Перевіряє, чи має користувач достатньо кредитів для операції.
     */
    public static function hasEnoughCredits(\User $user, int $amount): bool
    {
        return $user->credits >= $amount;
    }

    // ═══════════════════════════════════════════════════════════
    // Фасад: делегування до Command-класів
    // ═══════════════════════════════════════════════════════════

    /**
     * Списує кредити з балансу користувача.
     *
     * @see DeductCreditsCommand::execute()
     */
    public static function deduct(
        \User $user,
        int $amount,
        string $type,
        ?string $description = null,
        ?string $pasteId = null,
        ?string $orderId = null,
        ?string $service = null,
        ?string $idempotencyKey = null
    ): bool {
        return (new DeductCreditsCommand())->execute(
            $user, $amount, $type, $description, $pasteId, $orderId, $service, $idempotencyKey
        );
    }

    /**
     * Нараховує кредити на баланс користувача.
     *
     * @see CreditCreditsCommand::execute()
     */
    public static function credit(
        \User $user,
        int $amount,
        string $type,
        ?string $description = null,
        ?string $pasteId = null,
        ?string $orderId = null,
        ?string $service = null,
        ?string $idempotencyKey = null
    ): bool {
        return (new CreditCreditsCommand())->execute(
            $user, $amount, $type, $description, $pasteId, $orderId, $service, $idempotencyKey
        );
    }

    /**
     * Переказ кредитів між двома користувачами.
     *
     * @see TransferCreditsCommand::execute()
     */
    public static function transfer(
        \User $from,
        \User $to,
        int $amount,
        string $type,
        ?string $description = null,
        ?string $pasteId = null,
        ?string $service = null,
        ?string $idempotencyKey = null
    ): bool {
        return (new TransferCreditsCommand())->execute(
            $from, $to, $amount, $type, $description, $pasteId, $service, $idempotencyKey
        );
    }

    /**
     * Атомарно купує доступ до платної пасти.
     *
     * @see PurchaseAccessCommand::execute()
     */
    public static function purchasePasteAccess(
        \User $buyer,
        ?\User $author,
        string $pasteId,
        int $amount
    ): array {
        return (new PurchaseAccessCommand())->execute($buyer, $author, $pasteId, $amount);
    }
}
