<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Клас RateLimiter — Захист від брут-форсу та спаму
 * Використовує таблицю rate_limits у базі даних
 */
class RateLimiter {
    
    /**
     * Перевірка ліміту запитів
     * 
     * @param string $key Унікальний ключ дії (наприклад, 'login:127.0.0.1')
     * @param int $limit Максимальна кількість спроб у вікні
     * @param int $window Вікно часу в секундах
     * @return bool Повертає true, якщо ліміт НЕ перевищено (можна виконувати дію), інакше false
     */
    public static function check(string $key, int $limit = 10, int $window = 60): bool {
        $pdo = DB::getInstance()->getPDO();
        
        // 1. Очищення старих записів для цього ключа
        $cleanStmt = $pdo->prepare("DELETE FROM rate_limits WHERE action_key = ? AND created_at < NOW() - INTERVAL ? SECOND");
        $cleanStmt->execute([$key, $window]);
        
        // 2. Підрахунок поточних спроб у вікні
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE action_key = ?");
        $countStmt->execute([$key]);
        $count = (int) $countStmt->fetchColumn();
        
        // 3. Якщо ліміт не перевищено, записуємо нову спробу
        if ($count < $limit) {
            $insertStmt = $pdo->prepare("INSERT INTO rate_limits (action_key) VALUES (?)");
            $insertStmt->execute([$key]);
            return true; // Дозволяємо
        }
        
        return false; // Блокуємо
    }
}
