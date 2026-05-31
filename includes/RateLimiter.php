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
     * Повертає налаштування лімітів для вказаної дії.
     * Якщо конфігурація в config.php не знайдена, використовує дефолтні значення.
     */
    public static function getLimitConfig(string $action): array {
        if (defined('RATE_LIMITS') && isset(RATE_LIMITS[$action])) {
            return RATE_LIMITS[$action];
        }

        // Резервні значення за замовчуванням
        $defaults = [
            'login'          => ['limit' => 5,   'window' => 60,    'ip_limit' => 150],
            'register'       => ['limit' => 5,   'window' => 60,    'ip_limit' => 150],
            'create_paste'   => ['limit' => 5,   'window' => 60,    'ip_limit' => 120],
            'unlock_paste'   => ['limit' => 10,  'window' => 60,    'ip_limit' => 200],
            'api_auth'       => ['limit' => 5,   'window' => 60,    'ip_limit' => 150],
            'ad_verify'      => ['limit' => 12,  'window' => 60,    'ip_limit' => 300],
            'email_cooldown' => ['limit' => 1,   'window' => 180],
            'email_daily'    => ['limit' => 3,   'window' => 86400],
            'api_default'    => ['limit' => 60,  'window' => 60]
        ];

        return $defaults[$action] ?? ['limit' => 10, 'window' => 60, 'ip_limit' => 100];
    }

    /**
     * Перевіряє дію за основним ключем і точним по-IP лімітом.
     */
    public static function checkAction(string $action, ?int $limit = null, ?int $window = null, array $context = []): bool {
        $config = self::getLimitConfig($action);

        $limit = $limit ?? $config['limit'];
        $window = $window ?? $config['window'];
        $ipLimit = $context['ip_limit'] ?? $config['ip_limit'] ?? (int)max($limit * 20, $limit + 50);

        $primaryKey = self::buildActionKey($action, $context);

        if (!self::check($primaryKey, $limit, $window)) {
            error_log("RateLimiter deny primary action={$action}");
            return false;
        }

        // По-IP обмеження частоти: використовуємо точний IP клієнта без нормалізації до підмережі /24
        $ip = $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipKey = 'risk-ip:' . $action . ':' . $ip;

        if (!self::check($ipKey, $ipLimit, $window)) {
            error_log("RateLimiter deny ip_velocity action={$action} ip={$ip}");
            return false;
        }

        return true;
    }
    
    /**
     * Перевірка ліміту запитів (алгоритм Sliding Window Log з усуненням стану гонки)
     * 
     * @param string $key Унікальний ключ дії
     * @param int|null $limit Максимальна кількість спроб у вікні
     * @param int|null $window Вікно часу в секундах
     * @return bool Повертає true, якщо ліміт НЕ перевищено, інакше false
     */
    public static function check(string $key, ?int $limit = null, ?int $window = null): bool {
        if ($limit === null || $window === null) {
            // Парсимо назву дії з префікса ключа для пошуку в конфігурації
            $parts = explode(':', $key);
            $action = $parts[0] ?? '';
            if ($action === 'api') {
                $action = 'api_default';
            }
            $config = self::getLimitConfig($action);
            $limit = $limit ?? $config['limit'];
            $window = $window ?? $config['window'];
        }

        $pdo = DB::getInstance()->getPDO();
        
        // Wrap DELETE + INSERT + COUNT in a transaction to make them atomic
        // and prevent race conditions between concurrent requests
        $pdo->beginTransaction();
        try {
            // 1. Очищення старих записів для цього ключа
            $cleanStmt = $pdo->prepare("DELETE FROM rate_limits WHERE action_key = ? AND created_at < NOW() - INTERVAL ? SECOND");
            $cleanStmt->execute([$key, $window]);
            
            // 2. Вставляємо нову спробу
            $insertStmt = $pdo->prepare("INSERT INTO rate_limits (action_key) VALUES (?)");
            $insertStmt->execute([$key]);
            
            // 3. Підрахунок поточних спроб у вікні
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE action_key = ?");
            $countStmt->execute([$key]);
            $count = (int) $countStmt->fetchColumn();
            
            // 4. Якщо ліміт перевищено, відкочуємо транзакцію
            if ($count > $limit) {
                $pdo->rollBack();
                return false;
            }
            
            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
