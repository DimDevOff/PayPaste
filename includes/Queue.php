<?php
/**
 * Клас Queue — єдиний механізм постановки задач у чергу для зовнішніх інтеграцій.
 * Використовує MySQL як backend без зовнішніх сервісів черг.
 *
 * Типи задач:
 *   - moderation_check : перевірка контенту через OpenAI Moderation API
 *   - moderation_rewrite: перефразування контенту через Ollama
 *   - email_verify      : відправка коду верифікації email
 *   - email_changed     : повідомлення про зміну email
 *   - email_custom      : довільне email-повідомлення
 *
 * Стани задачі: queued → processing → completed | failed | dead
 */
class Queue {

    // Типи задач
    const TYPE_MODERATION_CHECK   = 'moderation_check';
    const TYPE_MODERATION_REWRITE = 'moderation_rewrite';
    const TYPE_EMAIL_VERIFY       = 'email_verify';
    const TYPE_EMAIL_CHANGED     = 'email_changed';
    const TYPE_EMAIL_CUSTOM      = 'email_custom';

    // Стани
    const STATUS_QUEUED     = 'queued';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';
    const STATUS_DEAD       = 'dead';

    // Конфігурація
    const DEFAULT_MAX_ATTEMPTS = 3;
    const BACKOFF_BASE_SECONDS = 30;   // Базова затримка ретраю
    const CLAIM_TIMEOUT_SECONDS = 300; // Час, після якого processing-задача вважається завислою
    const CLEANUP_OLDER_THAN_DAYS = 7;

    /**
     * Постановка задачі у чергу.
     *
     * @param string      $type           Тип задачі (self::TYPE_*)
     * @param array       $payload        Дані задачі (JSON-серіалізовані)
     * @param string|null $idempotencyKey Ключ ідемпотентності (запобігає дублюванню)
     * @param int         $maxAttempts    Максимальна кількість спроб
     * @param int         $delaySeconds   Затримка перед обробкою (0 = негайно)
     * @return string|null ID створеної задачі або null, якщо дублікат
     */
    public static function push(
        string $type,
        array $payload,
        ?string $idempotencyKey = null,
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $delaySeconds = 0
    ): ?string {
        $pdo = DB::getInstance()->getPDO();

        // Ідемпотентність: якщо задача з таким ключем вже існує — не створюємо дублікат
        if ($idempotencyKey !== null) {
            $stmt = $pdo->prepare("
                SELECT id FROM jobs 
                WHERE idempotency_key = ? 
                  AND status IN ('queued', 'processing') 
                LIMIT 1
            ");
            $stmt->execute([$idempotencyKey]);
            if ($stmt->fetch()) {
                error_log("Queue::push — дублікат за ідемпотентним ключем: $idempotencyKey");
                return null;
            }
        }

        $id = 'j_' . bin2hex(random_bytes(8));
        $scheduledAt = $delaySeconds > 0
            ? date('Y-m-d H:i:s', time() + $delaySeconds)
            : date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO jobs (id, type, status, payload, attempts, max_attempts, idempotency_key, scheduled_at)
            VALUES (?, ?, ?, ?, 0, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $id,
                $type,
                self::STATUS_QUEUED,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $maxAttempts,
                $idempotencyKey,
                $scheduledAt
            ]);
            return $id;
        } catch (\PDOException $e) {
            // Duplicate entry for idempotency_key
            if ($idempotencyKey !== null && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                error_log("Queue::push — дублікат за UNIQUE ключем: $idempotencyKey");
                return null;
            }
            error_log("Queue::push — помилка INSERT: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Отримання задач для обробки.
     *
     * @param int         $limit        Максимальна кількість задач
     * @param string|null $type         Фільтр за конкретним типом (опціонально)
     * @param array|null  $excludeTypes Масив типів задач для виключення (опціонально)
     * @return array Масив знайдених задач
     */
    public static function pop(int $limit = 5, ?string $type = null, ?array $excludeTypes = null): array {
        $pdo = DB::getInstance()->getPDO();

        // Звільняємо завислі задачі (processing занадто довго)
        self::releaseStuck($pdo);

        $whereType = $type !== null ? " AND type = ?" : "";
        $whereExclude = "";
        
        $selectParams = [];
        if ($type !== null) {
            $selectParams[] = $type;
        }

        if (!empty($excludeTypes)) {
            $placeholders = implode(',', array_fill(0, count($excludeTypes), '?'));
            $whereExclude = " AND type NOT IN ($placeholders)";
            $selectParams = array_merge($selectParams, $excludeTypes);
        }

        $selectParams[] = $limit;

        try {
            $pdo->beginTransaction();

            // Знаходимо доступні задачі потрібного типу, виключаючи вказані.
            $stmt = $pdo->prepare("
                SELECT * FROM jobs
                WHERE status = 'queued'
                  AND scheduled_at <= NOW()
                  AND attempts < max_attempts
                  $whereType
                  $whereExclude
                ORDER BY scheduled_at ASC
                LIMIT ?
                FOR UPDATE
            ");
            $stmt->execute($selectParams);
            $jobs = $stmt->fetchAll();

            if (empty($jobs)) {
                $pdo->commit();
                return [];
            }

            // Помічаємо тільки ще queued-задачі як processing (claim).
            $ids = array_column($jobs, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $updateStmt = $pdo->prepare("
                UPDATE jobs
                SET status = ?, started_at = NOW(), attempts = attempts + 1
                WHERE status = ?
                  AND id IN ($placeholders)
            ");
            $params = array_merge([self::STATUS_PROCESSING, self::STATUS_QUEUED], $ids);
            $updateStmt->execute($params);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Парсимо payload
        foreach ($jobs as &$job) {
            $job['attempts'] = (int)$job['attempts'] + 1;
            $job['payload'] = json_decode($job['payload'], true) ?: [];
        }

        return $jobs;
    }

    /**
     * Позначити задачу як завершену.
     */
    public static function complete(string $jobId): void {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            UPDATE jobs 
            SET status = ?, completed_at = NOW(), last_error = NULL
            WHERE id = ?
        ");
        $stmt->execute([self::STATUS_COMPLETED, $jobId]);
    }

    /**
     * Позначити задачу як невдалу (з retry або dead).
     * Обчислює час наступної спроби з експоненційним backoff.
     *
     * @param string $jobId ID задачі
     * @param string $error Опис помилки
     */
    public static function fail(string $jobId, string $error): void {
        $pdo = DB::getInstance()->getPDO();

        // Отримуємо поточні дані задачі
        $stmt = $pdo->prepare("SELECT attempts, max_attempts, type, payload FROM jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $row = $stmt->fetch();

        if (!$row) return;

        $attempts = (int)$row['attempts'];
        $maxAttempts = (int)$row['max_attempts'];
        $type = $row['type'];
        $payload = json_decode($row['payload'], true) ?: [];

        if ($attempts >= $maxAttempts) {
            // Вичерпано ліміт спроб → dead
            $stmt = $pdo->prepare("
                UPDATE jobs 
                SET status = ?, last_error = ? 
                WHERE id = ?
            ");
            $stmt->execute([self::STATUS_DEAD, $error, $jobId]);
            error_log("Queue::fail — задача $jobId перейшла у DEAD: $error");

            // Fallback для задач модерації: якщо OpenAI/Ollama недоступні після всіх спроб,
            // переводимо пасту у moderation_failed — контент не публікується автоматично
            self::handleDeadFallback($jobId, $type, $payload);
        } else {
            // Retry з експоненційним backoff
            $backoff = self::BACKOFF_BASE_SECONDS * pow(2, $attempts - 1);
            $scheduledAt = date('Y-m-d H:i:s', time() + $backoff);

            $stmt = $pdo->prepare("
                UPDATE jobs 
                SET status = ?, last_error = ?, scheduled_at = ?
                WHERE id = ?
            ");
            $stmt->execute([self::STATUS_QUEUED, $error, $scheduledAt, $jobId]);
            error_log("Queue::fail — задача $jobId retry (спроба $attempts/$maxAttempts), наступна через {$backoff}с: $error");
        }
    }

    /**
     * Отримати статус задачі.
     *
     * @param string $jobId ID задачі
     * @return array|null Статус або null
     */
    public static function getStatus(string $jobId): ?array {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            SELECT id, type, status, attempts, max_attempts, last_error, 
                   scheduled_at, started_at, completed_at, created_at
            FROM jobs WHERE id = ?
        ");
        $stmt->execute([$jobId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Отримати статус задачі за ідемпотентним ключем.
     *
     * @param string $key Ключ ідемпотентності
     * @return array|null
     */
    public static function getStatusByIdempotencyKey(string $key): ?array {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            SELECT id, type, status, attempts, max_attempts, last_error,
                   scheduled_at, started_at, completed_at, created_at
            FROM jobs WHERE idempotency_key = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$key]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Метрики черги: кількість задач за статусами, помилки, час виконання.
     *
     * @return array
     */
    public static function getMetrics(): array {
        $pdo = DB::getInstance()->getPDO();

        // Розподіл за статусами
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as cnt 
            FROM jobs 
            GROUP BY status
        ");
        $byStatus = [];
        while ($row = $stmt->fetch()) {
            $byStatus[$row['status']] = (int)$row['cnt'];
        }

        // Загальна кількість dead-задач
        $deadCount = $byStatus[self::STATUS_DEAD] ?? 0;

        // Середній час виконання (completed_at - started_at) для завершених
        $stmt = $pdo->query("
            SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration
            FROM jobs 
            WHERE status = 'completed' AND started_at IS NOT NULL AND completed_at IS NOT NULL
        ");
        $avgDuration = round((float)$stmt->fetchColumn(), 2);

        // Retry count: загальна кількість спроб (attempts) по задачах, де attempts > 1
        $stmt = $pdo->query("
            SELECT SUM(attempts) as total_retries 
            FROM jobs 
            WHERE attempts > 1
        ");
        $totalRetries = (int)$stmt->fetchColumn();

        // Останні помилки
        $stmt = $pdo->query("
            SELECT id, type, last_error, attempts, created_at 
            FROM jobs 
            WHERE status IN ('failed', 'dead') 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $recentErrors = $stmt->fetchAll();

        return [
            'by_status'      => $byStatus,
            'queue_length'   => $byStatus[self::STATUS_QUEUED] ?? 0,
            'dead_count'     => $deadCount,
            'avg_duration_s' => $avgDuration,
            'total_retries'  => $totalRetries,
            'recent_errors'  => $recentErrors,
        ];
    }

    /**
     * Очищення старих завершених/мертвих задач.
     *
     * @param int $olderThanDays Видаляти задачі старші за N днів
     * @return int Кількість видалених рядків
     */
    public static function cleanup(int $olderThanDays = self::CLEANUP_OLDER_THAN_DAYS): int {
        $pdo = DB::getInstance()->getPDO();
        $stmt = $pdo->prepare("
            DELETE FROM jobs 
            WHERE status IN ('completed', 'dead') 
              AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$olderThanDays]);
        return $stmt->rowCount();
    }

    /**
     * Звільнення завислих задач (processing занадто довго).
     * Повертає їх у статус queued для повторної обробки.
     */
    private static function releaseStuck(\PDO $pdo): void {
        $stmt = $pdo->prepare("
            UPDATE jobs 
            SET status = ?, started_at = NULL 
            WHERE status = ? 
              AND started_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([
            self::STATUS_QUEUED,
            self::STATUS_PROCESSING,
            self::CLAIM_TIMEOUT_SECONDS
        ]);

        $released = $stmt->rowCount();
        if ($released > 0) {
            error_log("Queue::releaseStuck — звільнено {$released} завислих задач");
        }
    }

    /**
     * Повертає список усіх типів задач (для використання в інших частинах системи).
     */
    public static function getTypes(): array {
        return [
            self::TYPE_MODERATION_CHECK,
            self::TYPE_MODERATION_REWRITE,
            self::TYPE_EMAIL_VERIFY,
            self::TYPE_EMAIL_CHANGED,
            self::TYPE_EMAIL_CUSTOM,
        ];
    }

    /**
     * Fallback-обробка для dead-задач: безпечна деградація.
     * Делегує до JobHandlers::handleDeadModerationFallback().
     */
    private static function handleDeadFallback(string $jobId, string $type, array $payload): void {
        require_once __DIR__ . '/JobHandlers.php';
        JobHandlers::handleDeadModerationFallback($jobId, $type, $payload);

        // Для email-задач: просто логування
        if (in_array($type, [self::TYPE_EMAIL_VERIFY, self::TYPE_EMAIL_CHANGED, self::TYPE_EMAIL_CUSTOM], true)) {
            error_log("Queue fallback: email-задача $jobId ($type) не виконана — провайдер недоступний");
        }
    }
}
