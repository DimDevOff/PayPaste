<?php

require_once __DIR__ . '/../TransactionManager.php';
require_once __DIR__ . '/../../models/Transaction.php';
require_once __DIR__ . '/../../models/User.php';

/**
 * PurchaseAccessCommand — атомарна купівля доступу до платної пасти.
 *
 * Витягнуто з CreditService::purchasePasteAccess(). Поведінка, повідомлення
 * про помилки та виклики error_log збережені без змін.
 */
class PurchaseAccessCommand
{
    /**
     * Атомарно купує доступ до платної пасти.
     * PRIMARY KEY (user_id, paste_id) у unlocked_pastes є idempotency guard.
     * Використовує FOR UPDATE для блокування балансу та доступу.
     *
     * @param User      $buyer   Покупець
     * @param User|null $author  Автор пасти
     * @param string    $pasteId ID пасти
     * @param int       $amount  Вартість доступу
     *
     * @return array Результат операції ['success' => bool, 'message' => string]
     * @throws Exception При помилці БД або недостатньому балансі
     */
    public function execute(
        \User $buyer,
        ?\User $author,
        string $pasteId,
        int $amount
    ): array {
        if ($amount <= 0) {
            throw new \Exception("Сума покупки має бути позитивною.");
        }

        if ($author && $author->id === $buyer->id) {
            return ['success' => false, 'message' => "Ви вже маєте доступ до цієї пасти!"];
        }

        return TransactionManager::run(function (\PDO $pdo) use ($buyer, $author, $pasteId, $amount): array {
            // Блокуємо баланс покупця
            $lockBuyer = $pdo->prepare("SELECT credits FROM users WHERE id = ? FOR UPDATE");
            $lockBuyer->execute([$buyer->id]);
            $currentCredits = $lockBuyer->fetchColumn();
            if ($currentCredits === false) {
                throw new \Exception("Користувача не знайдено!");
            }
            $currentCredits = (int)$currentCredits;

            // Блокуємо запис доступу — INSERT IGNORE для ідемпотентності
            $existingStmt = $pdo->prepare(
                "SELECT 1 FROM unlocked_pastes WHERE user_id = ? AND paste_id = ? FOR UPDATE"
            );
            $existingStmt->execute([$buyer->id, $pasteId]);
            if ($existingStmt->fetchColumn()) {
                return ['success' => false, 'message' => "Ви вже маєте доступ до цієї пасти!"];
            }

            if ($currentCredits < $amount) {
                return ['success' => false, 'message' => "Не вистачає кредитів для покупки доступу!"];
            }

            // Idempotent insert
            $unlockStmt = $pdo->prepare(
                "INSERT IGNORE INTO unlocked_pastes (user_id, paste_id) VALUES (?, ?)"
            );
            $unlockStmt->execute([$buyer->id, $pasteId]);
            if ($unlockStmt->rowCount() !== 1) {
                return ['success' => false, 'message' => "Ви вже маєте доступ до цієї пасти!"];
            }

            // Умовне списання
            $deductStmt = $pdo->prepare(
                "UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?"
            );
            $deductStmt->execute([$amount, $buyer->id, $amount]);
            if ($deductStmt->rowCount() !== 1) {
                throw new \Exception("Не вистачає кредитів для покупки доступу!");
            }

            \Transaction::create(
                $buyer->id,
                -$amount,
                'purchase',
                null,
                $pasteId,
                null,
                'Купівля доступу до пасти'
            );

            // Нарахування автору (якщо є)
            if ($author) {
                $creditStmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
                $creditStmt->execute([$amount, $author->id]);
                if ($creditStmt->rowCount() !== 1) {
                    throw new \Exception("Автора пасти не знайдено для нарахування.");
                }

                \Transaction::create(
                    $author->id,
                    $amount,
                    'sale',
                    null,
                    $pasteId,
                    null,
                    'Продаж доступу до пасти'
                );
            }

            // Оновлюємо об'єкти та кеш сесії
            $buyer->credits = $currentCredits - $amount;
            if (!in_array($pasteId, $buyer->unlocked_pastes)) {
                $buyer->unlocked_pastes[] = $pasteId;
            }
            TransactionManager::syncSessionCache($buyer);

            if ($author) {
                // Автор не обов'язково в поточній сесії — оновлюємо об'єкт
                $author->credits += $amount;
            }

            error_log("CreditService purchase accepted buyer={$buyer->id} paste={$pasteId} amount={$amount}");
            return ['success' => true, 'message' => "Доступ успішно придбано!"];
        });
    }
}
