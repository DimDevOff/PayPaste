<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Сервіс для управління кредитами користувачів.
 * Відповідає за списання, нарахування, запис транзакцій та фінансові операції.
 */
class CreditService {

    /**
     * Розраховує вартість створення платної пасти на основі довжини контенту.
     * Формула: ceil(довжина / 10)
     *
     * @param string $content Текст пасти
     * @return int Вартість у кредитах
     */
    public static function calculateCreationCost(string $content): int {
        return (int) ceil(mb_strlen($content) / 10);
    }

    /**
     * Перевіряє, чи має користувач достатньо кредитів для операції.
     *
     * @param User $user Об'єкт користувача
     * @param int $amount Потрібна сума
     * @return bool true якщо кредитів достатньо
     */
    public static function hasEnoughCredits(User $user, int $amount): bool {
        return $user->credits >= $amount;
    }

    /**
     * Списує кредити з балансу користувача та створює транзакцію.
     * Використовує PDO-транзакцію для атомарності.
     *
     * @param User $user Користувач
     * @param int $amount Сума списання (позитивне число)
     * @param string $type Тип транзакції (creation_fee, purchase, api_usage)
     * @param string|null $description Опис операції
     * @param string|null $pasteId ID пов'язаної пасти
     * @param string|null $orderId ID пов'язаного замовлення
     * @return bool Успішність операції
     * @throws Exception При помилці БД або недостатньому балансі
     */
    public static function deduct(User $user, int $amount, string $type, ?string $description = null, ?string $pasteId = null, ?string $orderId = null): bool {
        if ($amount <= 0) {
            throw new Exception("Сума списання має бути позитивною.");
        }

        if (!self::hasEnoughCredits($user, $amount)) {
            throw new Exception("Недостатньо кредитів. Потрібно {$amount}, а у вас {$user->credits}.");
        }

        $pdo = DB::getInstance()->getPDO();
        $pdo->beginTransaction();

        try {
            $user->credits -= $amount;
            $user->save();

            $tx = new Transaction([
                'user_id' => $user->id,
                'amount' => -$amount,
                'type' => $type,
                'related_paste_id' => $pasteId,
                'related_order_id' => $orderId,
                'description' => $description ?? self::defaultDescription($type)
            ]);
            $tx->save();

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Нараховує кредити на баланс користувача та створює транзакцію.
     * Використовує PDO-транзакцію для атомарності.
     *
     * @param User $user Користувач (отримувач)
     * @param int $amount Сума нарахування (позитивне число)
     * @param string $type Тип транзакції (sale, topup)
     * @param string|null $description Опис операції
     * @param string|null $pasteId ID пов'язаної пасти
     * @param string|null $orderId ID пов'язаного замовлення
     * @return bool Успішність операції
     * @throws Exception При помилці БД
     */
    public static function credit(User $user, int $amount, string $type, ?string $description = null, ?string $pasteId = null, ?string $orderId = null): bool {
        if ($amount <= 0) {
            throw new Exception("Сума нарахування має бути позитивною.");
        }

        $pdo = DB::getInstance()->getPDO();
        $pdo->beginTransaction();

        try {
            $user->credits += $amount;
            $user->save();

            $tx = new Transaction([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'related_paste_id' => $pasteId,
                'related_order_id' => $orderId,
                'description' => $description ?? self::defaultDescription($type)
            ]);
            $tx->save();

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Переказ кредитів від одного користувача до іншого з записом обох транзакцій.
     * Атомарна операція через PDO-транзакцію.
     *
     * @param User $from Відправник
     * @param User $to Отримувач
     * @param int $amount Сума
     * @param string $type Тип транзакції (purchase/sale)
     * @param string|null $description Опис
     * @param string|null $pasteId ID пов'язаної пасти
     * @return bool Успішність операції
     * @throws Exception При помилці БД або недостатньому балансі
     */
    public static function transfer(User $from, User $to, int $amount, string $type, ?string $description = null, ?string $pasteId = null): bool {
        if ($amount <= 0) {
            throw new Exception("Сума переказу має бути позитивною.");
        }

        if (!self::hasEnoughCredits($from, $amount)) {
            throw new Exception("Недостатньо кредитів для переказу.");
        }

        $pdo = DB::getInstance()->getPDO();
        $pdo->beginTransaction();

        try {
            // Списання у відправника
            $from->credits -= $amount;
            $from->save();

            $txFrom = new Transaction([
                'user_id' => $from->id,
                'amount' => -$amount,
                'type' => $type,
                'related_paste_id' => $pasteId,
                'description' => $description ?? self::defaultDescription($type)
            ]);
            $txFrom->save();

            // Нарахування отримувачу
            $to->credits += $amount;
            $to->save();

            $txTo = new Transaction([
                'user_id' => $to->id,
                'amount' => $amount,
                'type' => 'sale',
                'related_paste_id' => $pasteId,
                'description' => $description ?? self::defaultDescription('sale')
            ]);
            $txTo->save();

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Повертає стандартний опис транзакції за типом.
     *
     * @param string $type Тип транзакції
     * @return string Опис
     */
    private static function defaultDescription(string $type): string {
        $map = [
            'topup' => 'Поповнення балансу',
            'creation_fee' => 'Плата за створення платної пасти',
            'purchase' => 'Купівля доступу до пасти',
            'sale' => 'Продаж доступу до пасти',
            'api_usage' => 'Використання API'
        ];
        return $map[$type] ?? 'Фінансова операція';
    }
}
