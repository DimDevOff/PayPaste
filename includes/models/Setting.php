<?php
/**
 * Клас для роботи з глобальними налаштуваннями системи.
 * Значення зберігаються у таблиці settings (ключ-значення).
 */
class Setting {
    /**
     * Отримати значення налаштування.
     *
     * @param string $key Ключ
     * @param mixed $default Значення за замовчуванням
     * @return string|null
     */
    public static function get(string $key, $default = null): ?string {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }

    /**
     * Встановити значення налаштування.
     *
     * @param string $key Ключ
     * @param string $value Значення
     */
    public static function set(string $key, string $value): void {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `value` = ?, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$key, $value, $value]);
    }

    /**
     * Отримати значення як булеве.
     *
     * @param string $key Ключ
     * @param bool $default Значення за замовчуванням
     * @return bool
     */
    public static function getBool(string $key, bool $default = false): bool {
        $val = self::get($key);
        if ($val === null) {
            return $default;
        }
        return in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Перевірити, чи ввімкнено строгий режим модерації.
     * У строгому режимі: необхідна повна (зовнішня) перевірка для публікації.
     * У легкому режимі: достатньо локальної перевірки — паста публікується якщо OpenAI недоступний.
     *
     * @return bool true — строгий режим, false — легкий
     */
    public static function isModerationStrict(): bool {
        return self::getBool('moderation_strict_mode', true);
    }
}
