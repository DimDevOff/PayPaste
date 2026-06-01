<?php
require_once __DIR__ . '/../RateLimiter.php';

/**
 * Серверно-авторитативна модель рекламного квесту.
 * Клієнт отримує підписаний одноразовий токен, але прогрес і replay-захист
 * живуть у БД (ad_events). Сесія використовується лише як кеш quest_id.
 *
 * Інваріанти:
 * - Одна валідна рекламна подія = одне зарахування (UNIQUE nonce у ad_events)
 * - Повторний виклик з тим самим nonce → INSERT IGNORE → дублікат не створюється
 * - Підміна або ресет сесії не дозволяє отримати повторне нарахування,
 *   бо nonce вже записаний у БД
 * - Для анонімних користувачів ключем є user_session_hash (відбиток браузера + сесії)
 */
class AdQuestService {
    public const REQUIRED_EVENTS = 3;
    private const TOKEN_TTL = 900;

    /** Мінімальний інтервал між рекламними подіями (секунд). 0 для тестування. */
    private static int $minSecondsBetweenEvents = 10;

    public static function setMinSecondsBetweenEvents(int $seconds): void {
        self::$minSecondsBetweenEvents = $seconds;
    }

    public static function getMinSecondsBetweenEvents(): int {
        return self::$minSecondsBetweenEvents;
    }

    /**
     * Обчислює стабільний хеш ідентичності для ad_events.
     * Для авторизованих — user_id, для анонімів — session fingerprint.
     */
    public static function identityHash(?string $userId = null): string {
        if ($userId) {
            return hash('sha256', 'user:' . $userId);
        }
        return hash('sha256', 'session:' . RateLimiter::sessionFingerprint());
    }

    /**
     * Повертає або створює quest_id для пасти.
     * Зберігається у сесії як кеш (не є критичним — БД є авторитетом).
     */
    public static function getQuestId(string $pasteId, ?string $userId = null): string {
        self::ensureSession();
        if (empty($_SESSION['ad_quests'][$pasteId]['quest_id'])) {
            $_SESSION['ad_quests'][$pasteId]['quest_id'] = bin2hex(random_bytes(16));
            $_SESSION['ad_quests'][$pasteId]['user_id'] = $userId;
            $_SESSION['ad_quests'][$pasteId]['started_at'] = time();
        }
        return $_SESSION['ad_quests'][$pasteId]['quest_id'];
    }

    /**
     * Створює короткоживучий токен для наступної рекламної події.
     * Токен підписаний HMAC, містить nonce для одноразовості.
     */
    public static function issueToken(string $pasteId, ?string $userId = null): string {
        self::ensureSession();
        $questId = self::getQuestId($pasteId, $userId);
        $nextStep = self::countCompletedFromDb($pasteId, $userId) + 1;

        $payload = [
            'paste_id' => $pasteId,
            'user_id' => $userId,
            'identity_hash' => self::identityHash($userId),
            'session_fp' => RateLimiter::sessionFingerprint(),
            'step' => $nextStep,
            'nonce' => bin2hex(random_bytes(16)),
            'quest_id' => $questId,
            'issued_at' => time(),
            'expires_at' => time() + self::TOKEN_TTL
        ];

        // Зберігаємо виданий nonce у сесії для швидкої перевірки
        if (empty($_SESSION['ad_quests'][$pasteId]['issued'])) {
            $_SESSION['ad_quests'][$pasteId]['issued'] = [];
        }
        $_SESSION['ad_quests'][$pasteId]['issued'][$payload['nonce']] = [
            'step' => $payload['step'],
            'expires_at' => $payload['expires_at']
        ];

        return self::encode($payload);
    }

    /**
     * Повертає кількість валідно зарахованих рекламних подій з БД.
     * Це авторитетне джерело правди — сесія може бути підроблена, БД — ні.
     */
    public static function progress(string $pasteId, ?string $userId = null): int {
        return self::countCompletedFromDb($pasteId, $userId);
    }

    /**
     * Чи завершив поточний квест доступ до пасти.
     */
    public static function hasAccess(string $pasteId, ?string $userId = null): bool {
        return self::progress($pasteId, $userId) >= self::REQUIRED_EVENTS;
    }

    /**
     * Валідує і зараховує одну рекламну подію рівно один раз.
     * Використовує INSERT IGNORE INTO ad_events для ідемпотентності.
     *
     * @return array Результат з success, ads_watched, remaining, done, reason (якщо відхилено)
     */
    public static function verifyEvent(string $pasteId, string $token, ?string $userId = null): array {
        self::ensureSession();

        // Rate limit: комбінований — користувач/сесія + м'який IP-фактор
        if (!RateLimiter::checkAction('ad_verify', 12, 60, ['user_id' => $userId, 'ip_limit' => 300])) {
            return self::reject($pasteId, 'rate_limited', 'Занадто багато рекламних підтверджень. Спробуйте пізніше.', $userId);
        }

        // 1. Розшифрування та перевірка підпису
        $payload = self::decode($token);
        if (!$payload) {
            return self::reject($pasteId, 'bad_signature', 'Некоректний рекламний токен.', $userId);
        }

        // 2. Перевірка paste_id
        if (($payload['paste_id'] ?? '') !== $pasteId) {
            return self::reject($pasteId, 'paste_mismatch', 'Токен не належить цій пасті.', $userId);
        }

        // 3. Перевірка user_id
        if (($payload['user_id'] ?? null) !== $userId) {
            return self::reject($pasteId, 'user_mismatch', 'Токен не належить поточному користувачу.', $userId);
        }

        // 4. Перевірка identity_hash (стабільний ідентифікатор для БД)
        $expectedIdentityHash = self::identityHash($userId);
        if (($payload['identity_hash'] ?? '') !== $expectedIdentityHash) {
            return self::reject($pasteId, 'identity_mismatch', 'Ідентичність не збігається з виданим токеном.', $userId);
        }

        // 5. Перевірка session fingerprint (додатковий фактор)
        if (($payload['session_fp'] ?? '') !== RateLimiter::sessionFingerprint()) {
            return self::reject($pasteId, 'fingerprint_mismatch', 'Сесія або браузер не збігаються з виданим токеном.', $userId);
        }

        // 6. Перевірка терміну дії токена
        if (($payload['expires_at'] ?? 0) < time()) {
            return self::reject($pasteId, 'expired', 'Рекламний токен застарів. Оновіть сторінку.', $userId);
        }

        // 7. Перевірка quest_id (відповідає поточному квесту)
        $questId = self::getQuestId($pasteId, $userId);
        if (($payload['quest_id'] ?? '') !== $questId) {
            return self::reject($pasteId, 'quest_mismatch', 'Токен належить іншому квесту.', $userId);
        }

        // 8. Перевірка nonce — наявність у запиті
        $nonce = (string)($payload['nonce'] ?? '');
        if ($nonce === '') {
            return self::reject($pasteId, 'missing_nonce', 'Рекламний токен не містить nonce.', $userId);
        }

        // 9. Перевірка replay у БД — авторитетне джерело правди
        // Якщо nonce вже зарахований у БД — це replay, незалежно від стану сесії
        $identityHash = self::identityHash($userId);
        if (self::isNonceInDb($pasteId, $identityHash, $nonce)) {
            return self::reject($pasteId, 'replay', 'Ця рекламна подія вже була зарахована.', $userId);
        }

        // 10. Перевірка nonce у сесії — чи був виданий сервером
        if (empty($_SESSION['ad_quests'][$pasteId]['issued'][$nonce])) {
            return self::reject($pasteId, 'unknown_nonce', 'Рекламна подія не була видана сервером.', $userId);
        }

        // 11. Перевірка чи nonce не прострочений
        if ($_SESSION['ad_quests'][$pasteId]['issued'][$nonce]['expires_at'] < time()) {
            unset($_SESSION['ad_quests'][$pasteId]['issued'][$nonce]);
            return self::reject($pasteId, 'issued_expired', 'Рекламний токен застарів. Оновіть сторінку.', $userId);
        }

        // 12. Захист від занадто швидкого підтвердження
        if ((time() - (int)$payload['issued_at']) < self::$minSecondsBetweenEvents) {
            return self::reject($pasteId, 'too_fast', 'Рекламну подію підтверджено занадто швидко.', $userId);
        }

        // 13. Перевірка порядку кроку
        $expectedStep = self::countCompletedFromDb($pasteId, $userId) + 1;
        if ((int)$payload['step'] !== $expectedStep) {
            return self::reject($pasteId, 'step_mismatch', 'Некоректний порядок рекламних подій.', $userId);
        }

        // 14. Зарахування у БД — INSERT IGNORE гарантує ідемпотентність
        $pdo = DB::getInstance()->getPDO();

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO ad_events (paste_id, user_id, user_session_hash, quest_id, nonce, step)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$pasteId, $userId, $identityHash, $questId, $nonce, $expectedStep]);

        if ($stmt->rowCount() === 0) {
            // Дублікат nonce — подія вже зарахована (replay або race condition)
            error_log("AdQuest replay blocked paste={$pasteId} nonce={$nonce}");
            return self::reject($pasteId, 'replay', 'Ця рекламна подія вже була зарахована.', $userId);
        }

        // Видаляємо використаний nonce з сесії
        unset($_SESSION['ad_quests'][$pasteId]['issued'][$nonce]);

        $progress = self::countCompletedFromDb($pasteId, $userId);
        error_log("AdQuest accept paste={$pasteId} step={$expectedStep} nonce={$nonce} progress={$progress}");

        return [
            'success' => true,
            'ads_watched' => $progress,
            'remaining' => max(0, self::REQUIRED_EVENTS - $progress),
            'done' => $progress >= self::REQUIRED_EVENTS,
            'next_token' => $progress >= self::REQUIRED_EVENTS ? null : self::issueToken($pasteId, $userId)
        ];
    }

    /**
     * Підраховує зараховані події з БД — авторитетне джерело правди.
     */
    private static function countCompletedFromDb(string $pasteId, ?string $userId = null): int {
        $pdo = DB::getInstance()->getPDO();
        $identityHash = self::identityHash($userId);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM ad_events
            WHERE paste_id = ? AND user_session_hash = ?
        ");
        $stmt->execute([$pasteId, $identityHash]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Перевіряє, чи nonce вже зарахований у БД (для виявлення replay).
     */
    private static function isNonceInDb(string $pasteId, string $identityHash, string $nonce): bool {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            SELECT 1 FROM ad_events
            WHERE paste_id = ? AND user_session_hash = ? AND nonce = ?
            LIMIT 1
        ");
        $stmt->execute([$pasteId, $identityHash, $nonce]);
        return $stmt->fetchColumn() !== false;
    }

    private static function reject(string $pasteId, string $reason, string $message, ?string $userId = null): array {
        $progress = self::countCompletedFromDb($pasteId, $userId);
        error_log("AdQuest reject paste={$pasteId} reason={$reason}");
        return [
            'success' => false,
            'message' => $message,
            'reason' => $reason,
            'ads_watched' => $progress,
            'remaining' => max(0, self::REQUIRED_EVENTS - $progress),
            'done' => false
        ];
    }

    private static function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
            session_start();
        }
    }

    private static function encode(array $payload): string {
        $body = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $body, self::secret(), true);
        return $body . '.' . self::base64UrlEncode($signature);
    }

    private static function decode(string $token): ?array {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$body, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $body, self::secret(), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($body), true);
        return is_array($payload) ? $payload : null;
    }

    private static function secret(): string {
        if (!defined('COOKIE_SECRET') || COOKIE_SECRET === '') {
            throw new \RuntimeException('COOKIE_SECRET не налаштовано в config.php');
        }
        return COOKIE_SECRET;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
