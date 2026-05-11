<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Клас RateLimiter — Захист від брут-форсу та спаму
 * Використовує таблицю rate_limits у базі даних
 */
class RateLimiter {
    /**
     * Нормалізує IP до менш точного сегмента, щоб IP був лише сигналом ризику.
     */
    public static function normalizeIpFactor(?string $ip = null): string {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed !== false) {
                return substr(bin2hex($packed), 0, 16) . '::/64';
            }
        }

        return 'unknown';
    }

    /**
     * Повертає стабільний серверний відбиток сесії для анонімних потоків.
     */
    public static function sessionFingerprint(): string {
        if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
            session_start();
        }

        if (empty($_SESSION['_risk_fingerprint'])) {
            $_SESSION['_risk_fingerprint'] = bin2hex(random_bytes(16));
        }

        $browserSignal = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        return hash('sha256', $_SESSION['_risk_fingerprint'] . '|' . $browserSignal);
    }

    /**
     * Будує ключ із кількох сигналів: дія, користувач/сесія, браузер і IP як допоміжний фактор.
     */
    public static function buildActionKey(string $action, array $context = []): string {
        $subject = $context['user_id'] ?? null;
        if ($subject) {
            $identity = 'user:' . $subject;
        } elseif (!empty($context['subject'])) {
            $identity = 'subject:' . hash('sha256', strtolower((string)$context['subject']));
        } else {
            $identity = 'session:' . self::sessionFingerprint();
        }

        $device = $context['device'] ?? hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        $ipFactor = $context['ip_factor'] ?? self::normalizeIpFactor($context['ip'] ?? null);

        return implode(':', [
            'risk',
            $action,
            hash('sha256', $identity),
            substr(hash('sha256', (string)$device), 0, 16),
            substr(hash('sha256', (string)$ipFactor), 0, 16)
        ]);
    }

    /**
     * Перевіряє дію за основним ключем і м'яким NAT/IP velocity-фактором.
     * IP-фактор має більший ліміт і сам по собі не блокує нормальних авторизованих користувачів.
     */
    public static function checkAction(string $action, int $limit = 10, int $window = 60, array $context = []): bool {
        $primaryKey = self::buildActionKey($action, $context);

        if (!self::check($primaryKey, $limit, $window)) {
            error_log("RateLimiter deny primary action={$action}");
            return false;
        }

        $ipLimit = (int)($context['ip_limit'] ?? max($limit * 20, $limit + 50));
        $ipKey = 'risk-ip:' . $action . ':' . self::normalizeIpFactor($context['ip'] ?? null);
        if (!self::check($ipKey, $ipLimit, $window)) {
            error_log("RateLimiter deny ip_velocity action={$action}");
            return false;
        }

        return true;
    }
    
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
