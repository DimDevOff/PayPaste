<?php

require_once __DIR__ . '/../TransactionManager.php';
require_once __DIR__ . '/../../models/Transaction.php';
require_once __DIR__ . '/../../models/User.php';

/**
 * CreditCreditsCommand — атомарне нарахування кредитів на баланс користувача.
 *
 * Витягнуто з CreditService::credit(). Поведінка, повідомлення про помилки
 * та виклики error_log збережені без змін.
 */
class CreditCreditsCommand
{
    /**
     * Нараховує кредити на баланс користувача та створює транзакцію.
     * Атомарна операція з FOR UPDATE.
     * Ідемпотентна при наявності idempotencyKey.
     *
     * @param User        $user           Користувач (отримувач)
     * @param int         $amount         Сума нарахування (позитивне число)
     * @param string      $type           Тип транзакції
     * @param string|null $description    Опис
     * @param string|null $pasteId        ID пасти
     * @param string|null $orderId        ID замовлення
     * @param string|null $service        Джерело
     * @param string|null $idempotencyKey Ключ ідемпотентності
     *
     * @return bool Успішність
     * @throws Exception При помилці БД
     */
    public function execute(
        \User $user,
        int $amount,
        string $type,
        ?string $description = null,
        ?string $pasteId = null,
        ?string $orderId = null,
        ?string $service = null,
        ?string $idempotencyKey = null
    ): bool {
        if ($amount <= 0) {
            throw new \Exception("Сума нарахування має бути позитивною.");
        }

        return TransactionManager::run(function (\PDO $pdo) use (
            $user, $amount, $type, $description, $pasteId, $orderId, $service, $idempotencyKey
        ): bool {
            // Ідемпотентність
            $existing = TransactionManager::checkIdempotency($pdo, $idempotencyKey);
            if ($existing !== null) {
                error_log("CreditService credit idempotent skip user={$user->id} key={$idempotencyKey}");
                return true;
            }

            // Блокуємо рядок користувача
            $lockStmt = $pdo->prepare("SELECT credits FROM users WHERE id = ? FOR UPDATE");
            $lockStmt->execute([$user->id]);
            $currentCredits = $lockStmt->fetchColumn();
            if ($currentCredits === false) {
                throw new \Exception("Користувача не знайдено!");
            }
            $currentCredits = (int)$currentCredits;

            $updateStmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
            $updateStmt->execute([$amount, $user->id]);
            if ($updateStmt->rowCount() !== 1) {
                throw new \Exception("Не вдалося нарахувати кредити.");
            }

            $tx = new \Transaction([
                'user_id'          => $user->id,
                'amount'           => $amount,
                'type'             => $type,
                'service'          => $service,
                'related_paste_id' => $pasteId,
                'related_order_id' => $orderId,
                'description'      => $description ?? TransactionManager::defaultDescription($type),
                'idempotency_key'  => $idempotencyKey,
            ]);
            $tx->save();

            // Оновлюємо об'єкт та кеш сесії
            $user->credits = $currentCredits + $amount;
            TransactionManager::syncSessionCache($user);

            error_log(
                "CreditService credit accepted user={$user->id} amount={$amount} type={$type}"
                . ($idempotencyKey ? " key={$idempotencyKey}" : "")
            );
            return true;
        });
    }
}
