<?php

require_once __DIR__ . '/../TransactionManager.php';
require_once __DIR__ . '/../../models/Transaction.php';
require_once __DIR__ . '/../../models/User.php';

/**
 * TransferCreditsCommand — атомарний переказ кредитів між двома користувачами.
 *
 * Витягнуто з CreditService::transfer(). Поведінка, повідомлення про помилки
 * та виклики error_log збережені без змін.
 */
class TransferCreditsCommand
{
    /**
     * Переказ кредитів від одного користувача до іншого з записом обох транзакцій.
     * Атомарна операція через PDO-транзакцію з FOR UPDATE.
     *
     * @param User        $from           Відправник
     * @param User        $to             Отримувач
     * @param int         $amount         Сума
     * @param string      $type           Тип транзакції (purchase/sale)
     * @param string|null $description    Опис
     * @param string|null $pasteId        ID пасти
     * @param string|null $service        Джерело
     * @param string|null $idempotencyKey Ключ ідемпотентності
     *
     * @return bool Успішність
     * @throws Exception При помилці БД або недостатньому балансі
     */
    public function execute(
        \User $from,
        \User $to,
        int $amount,
        string $type,
        ?string $description = null,
        ?string $pasteId = null,
        ?string $service = null,
        ?string $idempotencyKey = null
    ): bool {
        if ($amount <= 0) {
            throw new \Exception("Сума переказу має бути позитивною.");
        }

        return TransactionManager::run(function (\PDO $pdo) use (
            $from, $to, $amount, $type, $description, $pasteId, $service, $idempotencyKey
        ): bool {
            // Ідемпотентність
            $existing = TransactionManager::checkIdempotency($pdo, $idempotencyKey);
            if ($existing !== null) {
                error_log("CreditService transfer idempotent skip from={$from->id} key={$idempotencyKey}");
                return true;
            }

            // Блокуємо обидва баланси (у фіксованому порядку для запобігання deadlock)
            $ids = [$from->id, $to->id];
            sort($ids);
            $lockStmt = $pdo->prepare(
                "SELECT id, credits FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE"
            );
            $lockStmt->execute($ids);
            $lockedUsers = [];
            while ($row = $lockStmt->fetch(\PDO::FETCH_ASSOC)) {
                $lockedUsers[$row['id']] = (int)$row['credits'];
            }

            if (!isset($lockedUsers[$from->id])) {
                throw new \Exception("Відправника не знайдено!");
            }
            if ($lockedUsers[$from->id] < $amount) {
                throw new \Exception("Недостатньо кредитів для переказу.");
            }

            // Умовне списання
            $deductStmt = $pdo->prepare(
                "UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?"
            );
            $deductStmt->execute([$amount, $from->id, $amount]);
            if ($deductStmt->rowCount() !== 1) {
                throw new \Exception("Не вистачає кредитів для переказу (конкурентний запит).");
            }

            // Нарахування отримувачу
            $creditStmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
            $creditStmt->execute([$amount, $to->id]);
            if ($creditStmt->rowCount() !== 1) {
                throw new \Exception("Отримувача не знайдено для нарахування.");
            }

            // Транзакція списання
            $txFrom = new \Transaction([
                'user_id'          => $from->id,
                'amount'           => -$amount,
                'type'             => $type,
                'service'          => $service,
                'related_paste_id' => $pasteId,
                'description'      => $description ?? TransactionManager::defaultDescription($type),
                'idempotency_key'  => $idempotencyKey,
            ]);
            $txFrom->save();

            // Транзакція нарахування
            $txTo = new \Transaction([
                'user_id'          => $to->id,
                'amount'           => $amount,
                'type'             => 'sale',
                'service'          => $service,
                'related_paste_id' => $pasteId,
                'description'      => $description ?? TransactionManager::defaultDescription('sale'),
            ]);
            $txTo->save();

            // Оновлюємо об'єкти та кеш сесії
            $from->credits = $lockedUsers[$from->id] - $amount;
            $to->credits   = $lockedUsers[$to->id] + $amount;
            TransactionManager::syncSessionCache($from);
            TransactionManager::syncSessionCache($to);

            error_log(
                "CreditService transfer accepted from={$from->id} to={$to->id} amount={$amount}"
                . ($idempotencyKey ? " key={$idempotencyKey}" : "")
            );
            return true;
        });
    }
}
