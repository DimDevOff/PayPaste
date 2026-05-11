<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Сервіс для управління кредитами користувачів.
 * Відповідає за списання, нарахування, запис транзакцій та фінансові операції.
 *
 * Інваріанти:
 * - deduct/credit/transfer — атомарні через PDO-транзакції з FOR UPDATE
 * - умовне списання: UPDATE ... WHERE credits >= ? (захист від race conditions)
 * - ідемпотентність: повторний виклик з тим самим idempotency_key не змінює баланс
 * - одна валідна рекламна подія = одне нарахування
 * - незмінність балансу при невалідній або повторній події
 * - аудит: error_log для кожної фінансової події (прийнято / відхилено)
 */
class CreditService {
    /**
     * Відкриває транзакцію лише якщо її ще немає.
     */
    private static function beginTransactionIfNeeded(PDO $pdo): bool {
        if ($pdo->inTransaction()) {
            return false;
        }
        $pdo->beginTransaction();
        return true;
    }

    /**
     * Завершує транзакцію лише якщо її відкрив цей сервіс.
     */
    private static function commitIfNeeded(PDO $pdo, bool $ownsTransaction): void {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    /**
     * Відкат транзакції лише якщо її відкрив цей сервіс.
     */
    private static function rollBackIfNeeded(PDO $pdo, bool $ownsTransaction): void {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    /**
     * Перевіряє ідемпотентність: якщо транзакція з цим ключем вже існує — повертає її.
     * @return array|null Існуюча транзакція або null (можна виконувати)
     */
    private static function checkIdempotency(PDO $pdo, ?string $idempotencyKey): ?array {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return null;
        }

        $stmt = $pdo->prepare("SELECT id, amount, type FROM transactions WHERE idempotency_key = ? LIMIT 1");
        $stmt->execute([$idempotencyKey]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        return $existing ?: null;
    }

    /**
     * Розраховує вартість створення платної пасти на основі довжини контенту.
     * Формула: ceil(довжина / 10)
     */
    public static function calculateCreationCost(string $content): int {
        return (int) ceil(mb_strlen($content) / 10);
    }

    /**
     * Перевіряє, чи має користувач достатньо кредитів для операції.
     */
    public static function hasEnoughCredits(User $user, int $amount): bool {
        return $user->credits >= $amount;
    }

    /**
     * Списує кредити з балансу користувача та створює транзакцію.
     * Атомарна операція з FOR UPDATE та умовним UPDATE.
     * Ідемпотентна при наявності idempotencyKey.
     *
     * @param User $user Користувач
     * @param int $amount Сума списання (позитивне число)
     * @param string $type Тип транзакції
     * @param string|null $description Опис
     * @param string|null $pasteId ID пасти
     * @param string|null $orderId ID замовлення
     * @param string|null $service Джерело операції
     * @param string|null $idempotencyKey Ключ ідемпотентності
     * @return bool Успішність операції (true навіть при дублікаті — баланс не змінюється)
     * @throws Exception При помилці БД або недостатньому балансі
     */
    public static function deduct(User $user, int $amount, string $type, ?string $description = null, ?string $pasteId = null, ?string $orderId = null, ?string $service = null, ?string $idempotencyKey = null): bool {
        if ($amount <= 0) {
            throw new Exception("Сума списання має бути позитивною.");
        }

        $pdo = DB::getInstance()->getPDO();
        $ownsTransaction = self::beginTransactionIfNeeded($pdo);

        try {
            // Ідемпотентність: якщо операція вже виконана — нічого не робимо
            $existing = self::checkIdempotency($pdo, $idempotencyKey);
            if ($existing !== null) {
                error_log("CreditService deduct idempotent skip user={$user->id} key={$idempotencyKey}");
                self::commitIfNeeded($pdo, $ownsTransaction);
                return true;
            }

            // Блокуємо рядок користувача для атомарної перевірки балансу
            $lockStmt = $pdo->prepare("SELECT credits FROM users WHERE id = ? FOR UPDATE");
            $lockStmt->execute([$user->id]);
            $currentCredits = $lockStmt->fetchColumn();
            if ($currentCredits === false) {
                throw new Exception("Користувача не знайдено!");
            }
            $currentCredits = (int)$currentCredits;

            if ($currentCredits < $amount) {
                self::commitIfNeeded($pdo, $ownsTransaction);
                error_log("CreditService deduct insufficient user={$user->id} need={$amount} has={$currentCredits}");
                throw new Exception("Недостатньо кредитів. Потрібно {$amount}, а у вас {$currentCredits}.");
            }

            // Умовне списання — захист від race conditions
            $updateStmt = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
            $updateStmt->execute([$amount, $user->id, $amount]);
            if ($updateStmt->rowCount() !== 1) {
                throw new Exception("Не вистачає кредитів для списання (конкурентний запит).");
            }

            $tx = new Transaction([
                'user_id' => $user->id,
                'amount' => -$amount,
                'type' => $type,
                'service' => $service,
                'related_paste_id' => $pasteId,
                'related_order_id' => $orderId,
                'description' => $description ?? self::defaultDescription($type),
                'idempotency_key' => $idempotencyKey
            ]);
            $tx->save();

            // Оновлюємо об'єкт та кеш сесії
            $user->credits = $currentCredits - $amount;
            self::syncSessionCache($user);

            self::commitIfNeeded($pdo, $ownsTransaction);
            error_log("CreditService deduct accepted user={$user->id} amount={$amount} type={$type}" . ($idempotencyKey ? " key={$idempotencyKey}" : ""));
            return true;
        } catch (Exception $e) {
            self::rollBackIfNeeded($pdo, $ownsTransaction);
            error_log("CreditService deduct failed user={$user->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Нараховує кредити на баланс користувача та створює транзакцію.
     * Атомарна операція з FOR UPDATE.
     * Ідемпотентна при наявності idempotencyKey.
     *
     * @param User $user Користувач (отримувач)
     * @param int $amount Сума нарахування (позитивне число)
     * @param string $type Тип транзакції
     * @param string|null $description Опис
     * @param string|null $pasteId ID пасти
     * @param string|null $orderId ID замовлення
     * @param string|null $service Джерело
     * @param string|null $idempotencyKey Ключ ідемпотентності
     * @return bool Успішність
     * @throws Exception При помилці БД
     */
    public static function credit(User $user, int $amount, string $type, ?string $description = null, ?string $pasteId = null, ?string $orderId = null, ?string $service = null, ?string $idempotencyKey = null): bool {
        if ($amount <= 0) {
            throw new Exception("Сума нарахування має бути позитивною.");
        }

        $pdo = DB::getInstance()->getPDO();
        $ownsTransaction = self::beginTransactionIfNeeded($pdo);

        try {
            // Ідемпотентність
            $existing = self::checkIdempotency($pdo, $idempotencyKey);
            if ($existing !== null) {
                error_log("CreditService credit idempotent skip user={$user->id} key={$idempotencyKey}");
                self::commitIfNeeded($pdo, $ownsTransaction);
                return true;
            }

            // Блокуємо рядок користувача
            $lockStmt = $pdo->prepare("SELECT credits FROM users WHERE id = ? FOR UPDATE");
            $lockStmt->execute([$user->id]);
            $currentCredits = $lockStmt->fetchColumn();
            if ($currentCredits === false) {
                throw new Exception("Користувача не знайдено!");
            }
            $currentCredits = (int)$currentCredits;

            $updateStmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
            $updateStmt->execute([$amount, $user->id]);
            if ($updateStmt->rowCount() !== 1) {
                throw new Exception("Не вдалося нарахувати кредити.");
            }

            $tx = new Transaction([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'service' => $service,
                'related_paste_id' => $pasteId,
                'related_order_id' => $orderId,
                'description' => $description ?? self::defaultDescription($type),
                'idempotency_key' => $idempotencyKey
            ]);
            $tx->save();

            // Оновлюємо об'єкт та кеш сесії
            $user->credits = $currentCredits + $amount;
            self::syncSessionCache($user);

            self::commitIfNeeded($pdo, $ownsTransaction);
            error_log("CreditService credit accepted user={$user->id} amount={$amount} type={$type}" . ($idempotencyKey ? " key={$idempotencyKey}" : ""));
            return true;
        } catch (Exception $e) {
            self::rollBackIfNeeded($pdo, $ownsTransaction);
            error_log("CreditService credit failed user={$user->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Переказ кредитів від одного користувача до іншого з записом обох транзакцій.
     * Атомарна операція через PDO-транзакцію з FOR UPDATE.
     *
     * @param User $from Відправник
     * @param User $to Отримувач
     * @param int $amount Сума
     * @param string $type Тип транзакції (purchase/sale)
     * @param string|null $description Опис
     * @param string|null $pasteId ID пасти
     * @param string|null $service Джерело
     * @param string|null $idempotencyKey Ключ ідемпотентності
     * @return bool Успішність
     * @throws Exception При помилці БД або недостатньому балансі
     */
    public static function transfer(User $from, User $to, int $amount, string $type, ?string $description = null, ?string $pasteId = null, ?string $service = null, ?string $idempotencyKey = null): bool {
        if ($amount <= 0) {
            throw new Exception("Сума переказу має бути позитивною.");
        }

        $pdo = DB::getInstance()->getPDO();
        $ownsTransaction = self::beginTransactionIfNeeded($pdo);

        try {
            // Ідемпотентність
            $existing = self::checkIdempotency($pdo, $idempotencyKey);
            if ($existing !== null) {
                error_log("CreditService transfer idempotent skip from={$from->id} key={$idempotencyKey}");
                self::commitIfNeeded($pdo, $ownsTransaction);
                return true;
            }

            // Блокуємо обидва баланси (у фіксованому порядку для запобігання deadlock)
            $ids = [$from->id, $to->id];
            sort($ids);
            $lockStmt = $pdo->prepare("SELECT id, credits FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE");
            $lockStmt->execute($ids);
            $lockedUsers = [];
            while ($row = $lockStmt->fetch(PDO::FETCH_ASSOC)) {
                $lockedUsers[$row['id']] = (int)$row['credits'];
            }

            if (!isset($lockedUsers[$from->id])) {
                throw new Exception("Відправника не знайдено!");
            }
            if ($lockedUsers[$from->id] < $amount) {
                self::commitIfNeeded($pdo, $ownsTransaction);
                throw new Exception("Недостатньо кредитів для переказу.");
            }

            // Умовне списання
            $deductStmt = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
            $deductStmt->execute([$amount, $from->id, $amount]);
            if ($deductStmt->rowCount() !== 1) {
                throw new Exception("Не вистачає кредитів для переказу (конкурентний запит).");
            }

            // Нарахування отримувачу
            $creditStmt = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
            $creditStmt->execute([$amount, $to->id]);
            if ($creditStmt->rowCount() !== 1) {
                throw new Exception("Отримувача не знайдено для нарахування.");
            }

            // Транзакція списання
            $txFrom = new Transaction([
                'user_id' => $from->id,
                'amount' => -$amount,
                'type' => $type,
                'service' => $service,
                'related_paste_id' => $pasteId,
                'description' => $description ?? self::defaultDescription($type),
                'idempotency_key' => $idempotencyKey
            ]);
            $txFrom->save();

            // Транзакція нарахування
            $txTo = new Transaction([
                'user_id' => $to->id,
                'amount' => $amount,
                'type' => 'sale',
                'service' => $service,
                'related_paste_id' => $pasteId,
                'description' => $description ?? self::defaultDescription('sale')
            ]);
            $txTo->save();

            // Оновлюємо об'єкти та кеш сесії
            $from->credits = $lockedUsers[$from->id] - $amount;
            $to->credits = $lockedUsers[$to->id] + $amount;
            self::syncSessionCache($from);
            self::syncSessionCache($to);

            self::commitIfNeeded($pdo, $ownsTransaction);
            error_log("CreditService transfer accepted from={$from->id} to={$to->id} amount={$amount}" . ($idempotencyKey ? " key={$idempotencyKey}" : ""));
            return true;
        } catch (Exception $e) {
            self::rollBackIfNeeded($pdo, $ownsTransaction);
            error_log("CreditService transfer failed from={$from->id} to={$to->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Атомарно купує доступ до платної пасти.
     * PRIMARY KEY (user_id, paste_id) у unlocked_pastes є idempotency guard.
     * Використовує FOR UPDATE для блокування балансу та доступу.
     *
     * @param User $buyer Покупець
     * @param User|null $author Автор пасти
     * @param string $pasteId ID пасти
     * @param int $amount Вартість доступу
     * @return array Результат операції
     * @throws Exception При помилці БД або недостатньому балансі
     */
    public static function purchasePasteAccess(User $buyer, ?User $author, string $pasteId, int $amount): array {
        if ($amount <= 0) {
            throw new Exception("Сума покупки має бути позитивною.");
        }

        if ($author && $author->id === $buyer->id) {
            return ['success' => false, 'message' => "Ви вже маєте доступ до цієї пасти!"];
        }

        $pdo = DB::getInstance()->getPDO();
        $ownsTransaction = self::beginTransactionIfNeeded($pdo);

        try {
            // Блокуємо баланс покупця
            $lockBuyer = $pdo->prepare("SELECT credits FROM users WHERE id = ? FOR UPDATE");
            $lockBuyer->execute([$buyer->id]);
            $currentCredits = $lockBuyer->fetchColumn();
            if ($currentCredits === false) {
                throw new Exception("Користувача не знайдено!");
            }
            $currentCredits = (int)$currentCredits;

            // Блокуємо запис доступу — INSERT IGNORE для ідемпотентності
            $existingStmt = $pdo->prepare("SELECT 1 FROM unlocked_pastes WHERE user_id = ? AND paste_id = ? FOR UPDATE");
            $existingStmt->execute([$buyer->id, $pasteId]);
            if ($existingStmt->fetchColumn()) {
                self::commitIfNeeded($pdo, $ownsTransaction);
                return ['success' => false, 'message' => "Ви вже маєте доступ до цієї пасти!"];
            }

            if ($currentCredits < $amount) {
                self::commitIfNeeded($pdo, $ownsTransaction);
                return ['success' => false, 'message' => "Не вистачає кредитів для покупки доступу!"];
            }

            // Idempotent insert
            $unlockStmt = $pdo->prepare("INSERT IGNORE INTO unlocked_pastes (user_id, paste_id) VALUES (?, ?)");
            $unlockStmt->execute([$buyer->id, $pasteId]);
            if ($unlockStmt->rowCount() !== 1) {
                self::commitIfNeeded($pdo, $ownsTransaction);
                return ['success' => false, 'message' => "Ви вже маєте доступ до цієї пасти!"];
            }

            // Умовне списання
            $deductStmt = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
            $deductStmt->execute([$amount, $buyer->id, $amount]);
            if ($deductStmt->rowCount() !== 1) {
                throw new Exception("Не вистачає кредитів для покупки доступу!");
            }

            Transaction::create(
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
                    throw new Exception("Автора пасти не знайдено для нарахування.");
                }

                Transaction::create(
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
            self::syncSessionCache($buyer);

            if ($author) {
                // Автор не обов'язково в поточній сесії — оновлюємо об'єкт
                $author->credits += $amount;
            }

            self::commitIfNeeded($pdo, $ownsTransaction);
            error_log("CreditService purchase accepted buyer={$buyer->id} paste={$pasteId} amount={$amount}");
            return ['success' => true, 'message' => "Доступ успішно придбано!"];
        } catch (Exception $e) {
            self::rollBackIfNeeded($pdo, $ownsTransaction);
            error_log("CreditService purchase rejected buyer={$buyer->id} paste={$pasteId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Синхронізує кеш користувача у сесії з актуальними даними.
     */
    private static function syncSessionCache(User $user): void {
        if (isset($_SESSION['user_id'], $_SESSION['_user_cache']) && $_SESSION['user_id'] === $user->id) {
            $_SESSION['_user_cache']->credits = $user->credits;
            if (property_exists($user, 'unlocked_pastes')) {
                $_SESSION['_user_cache']->unlocked_pastes = $user->unlocked_pastes;
            }
        }
    }

    /**
     * Повертає стандартний опис транзакції за типом.
     */
    private static function defaultDescription(string $type): string {
        $map = [
            'topup' => 'Поповнення балансу',
            'creation_fee' => 'Плата за створення платної пасти',
            'purchase' => 'Купівля доступу до пасти',
            'sale' => 'Продаж доступу до пасти',
            'api_usage' => 'Використання API',
            'ad_reward' => 'Нагорода за перегляд реклами'
        ];
        return $map[$type] ?? 'Фінансова операція';
    }
}
