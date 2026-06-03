<?php
/**
 * JobHandlers — єдиний клас-обробник для задач з черги.
 *
 * Консолідує логіку, яка раніше була розмазана по 3 місцях:
 * cron/worker.php (глобальні функції), bootstrap.php (inline-обробники),
 * Queue.php::handleDeadFallback().
 *
 * Кожен метод приймає callable $log для кастомного логування.
 * Використання:
 *   JobHandlers::handleModerationCheck($payload, 'workerLog');
 *   JobHandlers::handleModerationCheck($payload, 'error_log');
 */
require_once __DIR__ . '/Moderation.php';
require_once __DIR__ . '/mailer.php';

class JobHandlers {

    // ─── Константи (шляхи до шаблонів) ───
    const TEMPLATE_VERIFY   = __DIR__ . '/../templates/email_verify.html';
    const TEMPLATE_CHANGED  = __DIR__ . '/../templates/email_changed.html';

    // ─── МОДЕРАЦІЯ: CHECK ───

    /**
     * Обробка задачі модерації (OpenAI Moderation API).
     * Оновлює moderation_status пасти залежно від результату.
     *
     * @param array    $payload { paste_id, content }
     * @param callable $log     Функція логування (workerLog, error_log, або null)
     * @throws \InvalidArgumentException
     */
    public static function handleModerationCheck(array $payload, callable $log = null): void {
        $log ??= function($msg) { error_log("JobHandlers: $msg"); };
        $pasteId = $payload['paste_id'] ?? null;
        $content = $payload['content'] ?? '';

        if (!$pasteId) {
            throw new \InvalidArgumentException('Відсутній paste_id у payload');
        }

        $pdo = DB::getInstance()->getPDO();

        // Перевіряємо, що паста ще існує
        $stmt = $pdo->prepare("SELECT id, moderation_status FROM pastes WHERE id = ?");
        $stmt->execute([$pasteId]);
        $paste = $stmt->fetch();

        if (!$paste) {
            $log("moderation_check: пасту $pasteId не знайдено — пропускаємо");
            return;
        }

        // Ідемпотентність
        if ($paste['moderation_status'] !== 'pending') {
            $log("moderation_check: паста $pasteId вже має статус '{$paste['moderation_status']}' — пропускаємо");
            return;
        }

        try {
            $violations = Moderation::checkExternal($content);
        } catch (\Throwable $e) {
            // OpenAI недоступний — публікуємо автоматично (локальної перевірки достатньо)
            $pdo->prepare("UPDATE pastes SET moderation_status = 'approved' WHERE id = ?")->execute([$pasteId]);
            $log("moderation_check: паста $pasteId APPROVED (OpenAI недоступний: " . $e->getMessage() . ")");
            return;
        }

        if ($violations === false) {
            $pdo->prepare("UPDATE pastes SET moderation_status = 'approved' WHERE id = ?")->execute([$pasteId]);
            $log("moderation_check: паста $pasteId APPROVED (OpenAI чисто)");
        } else {
            $pdo->prepare("UPDATE pastes SET moderation_status = 'rejected', moderation_result = ? WHERE id = ?")
                ->execute([json_encode($violations, JSON_UNESCAPED_UNICODE), $pasteId]);
            $log("moderation_check: паста $pasteId REJECTED: " . implode(', ', $violations));
        }
    }

    // ─── МОДЕРАЦІЯ: REWRITE ───

    /**
     * Обробка задачі AI-перефразування (Ollama).
     *
     * @param array    $payload { paste_id, content }
     * @param callable $log
     * @throws \Throwable
     */
    public static function handleModerationRewrite(array $payload, callable $log = null): void {
        $log ??= function($msg) { error_log("JobHandlers: $msg"); };
        $pasteId = $payload['paste_id'] ?? null;
        $content = $payload['content'] ?? '';

        if (!$pasteId) {
            throw new \InvalidArgumentException('Відсутній paste_id у payload');
        }

        $pdo = DB::getInstance()->getPDO();

        $stmt = $pdo->prepare("SELECT id, content FROM pastes WHERE id = ? AND is_pending_rewrite = 1");
        $stmt->execute([$pasteId]);
        $paste = $stmt->fetch();

        if (!$paste) {
            $log("moderation_rewrite: пасту $pasteId не знайдено або вже оброблено — пропускаємо");
            return;
        }

        // Перефразування
        $rewritten = Moderation::rewrite($paste['content']);

        // Локальна перевірка результату
        $localViolations = Moderation::localCheck($rewritten);
        if ($localViolations) {
            $pdo->prepare("
                UPDATE pastes
                SET is_pending_rewrite = 0, moderation_status = 'rejected', moderation_result = ?
                WHERE id = ?
            ")->execute([json_encode($localViolations, JSON_UNESCAPED_UNICODE), $pasteId]);
            $log("moderation_rewrite: паста $pasteId REJECTED після переписування (локальна перевірка): " . implode(', ', $localViolations));
            return;
        }

        // Зовнішня перевірка
        try {
            $externalViolations = Moderation::checkExternal($rewritten);
        } catch (\Throwable $e) {
            // Якщо зовнішня недоступна — ставимо в чергу moderation_check
            $pdo->prepare("
                UPDATE pastes
                SET content = ?, is_pending_rewrite = 0, moderation_status = 'pending'
                WHERE id = ?
            ")->execute([$rewritten, $pasteId]);

            try {
                Queue::push(
                    Queue::TYPE_MODERATION_CHECK,
                    ['paste_id' => $pasteId, 'content' => $rewritten],
                    'mod_check:' . $pasteId
                );
                $log("moderation_rewrite: паста $pasteId переписана, зовнішня перевірка недоступна — поставлено у чергу moderation_check");
            } catch (\Throwable $pushErr) {
                $log("moderation_rewrite: паста $pasteId переписана, але не вдалося поставити moderation_check: " . $pushErr->getMessage());
            }
            return;
        }

        if ($externalViolations) {
            $pdo->prepare("
                UPDATE pastes
                SET is_pending_rewrite = 0, moderation_status = 'rejected', moderation_result = ?
                WHERE id = ?
            ")->execute([json_encode($externalViolations, JSON_UNESCAPED_UNICODE), $pasteId]);
            $log("moderation_rewrite: паста $pasteId REJECTED після переписування (OpenAI): " . implode(', ', $externalViolations));
            return;
        }

        // Усі перевірки пройдені
        $pdo->prepare("
            UPDATE pastes
            SET content = ?, is_pending_rewrite = 0, moderation_status = 'approved'
            WHERE id = ?
        ")->execute([$rewritten, $pasteId]);

        // Оновлюємо теги
        $pasteObj = Repo::pastes()->findById($pasteId);
        if ($pasteObj) {
            Repo::pastes()->syncTags($pasteId);
        }

        $log("moderation_rewrite: паста $pasteId перефразована, пройшла повторну модерацію — approved");
    }

    // ─── EMAIL: VERIFY ───

    /**
     * Відправка коду верифікації email.
     */
    public static function handleEmailVerify(array $payload, callable $log = null): void {
        $log ??= function($msg) { error_log("JobHandlers: $msg"); };
        $to   = $payload['to'] ?? '';
        $code = $payload['code'] ?? '';

        if (empty($to) || empty($code)) {
            throw new \InvalidArgumentException('Відсутній to/code у payload email_verify');
        }

        $template = file_get_contents(self::TEMPLATE_VERIFY);
        $html = str_replace('{{CODE}}', htmlspecialchars($code), $template);

        $result = Mailer::sendDirect($to, 'Підтвердження пошти — PayPaste', $html);
        if (!$result) {
            throw new \RuntimeException("Не вдалося відправити верифікаційний лист на $to");
        }

        $log("email_verify: лист надіслано на $to");
    }

    // ─── EMAIL: CHANGED ───

    /**
     * Повідомлення про зміну email.
     */
    public static function handleEmailChanged(array $payload, callable $log = null): void {
        $log ??= function($msg) { error_log("JobHandlers: $msg"); };
        $oldEmail = $payload['old_email'] ?? '';
        $newEmail = $payload['new_email'] ?? '';

        if (empty($oldEmail) || empty($newEmail)) {
            throw new \InvalidArgumentException('Відсутній old_email/new_email у payload');
        }

        $template = file_get_contents(self::TEMPLATE_CHANGED);
        $html = str_replace('{{NEW_EMAIL}}', htmlspecialchars($newEmail), $template);

        $result = Mailer::sendDirect($oldEmail, 'Зміна email-адреси — PayPaste', $html);
        if (!$result) {
            throw new \RuntimeException("Не вдалося відправити повідомлення про зміну email на $oldEmail");
        }

        $log("email_changed: повідомлення надіслано на $oldEmail");
    }

    // ─── EMAIL: CUSTOM ───

    /**
     * Довільне email-повідомлення.
     */
    public static function handleEmailCustom(array $payload, callable $log = null): void {
        $log ??= function($msg) { error_log("JobHandlers: $msg"); };
        $to      = $payload['to'] ?? '';
        $subject = $payload['subject'] ?? '';
        $html    = $payload['html'] ?? '';

        if (empty($to) || empty($subject)) {
            throw new \InvalidArgumentException('Відсутній to/subject у payload email_custom');
        }

        $result = Mailer::sendDirect($to, $subject, $html);
        if (!$result) {
            throw new \RuntimeException("Не вдалося відправити лист на $to");
        }

        $log("email_custom: лист надіслано на $to");
    }

    // ─── DEAD-LETTER FALLBACK ───

    /**
     * Fallback для dead-задач модерації.
     * Логіку винесено сюди з Queue::handleDeadFallback().
     */
    public static function handleDeadModerationFallback(string $jobId, string $type, array $payload): void {
        $pdo = DB::getInstance()->getPDO();

        if ($type === Queue::TYPE_MODERATION_CHECK) {
            $pasteId = $payload['paste_id'] ?? null;
            if (!$pasteId) return;

            // OpenAI недоступний після всіх спроб — публікуємо автоматично
            $stmt = $pdo->prepare("
                UPDATE pastes 
                SET moderation_status = 'approved', moderation_result = NULL
                WHERE id = ? AND moderation_status = 'pending'
            ");
            $stmt->execute([$pasteId]);
            if ($stmt->rowCount() > 0) {
                error_log("Queue fallback: пасту $pasteId авто-схвалено у approved (OpenAI недоступний після всіх спроб)");
            }
        } elseif ($type === Queue::TYPE_MODERATION_REWRITE) {
            $pasteId = $payload['paste_id'] ?? null;
            if (!$pasteId) return;

            // Ollama недоступний після всіх спроб — публікуємо оригінал
            $stmt = $pdo->prepare("
                UPDATE pastes 
                SET is_pending_rewrite = 0, moderation_status = 'approved', moderation_result = NULL
                WHERE id = ? AND is_pending_rewrite = 1
            ");
            $stmt->execute([$pasteId]);
            if ($stmt->rowCount() > 0) {
                error_log("Queue fallback: пасту $pasteId оригінал авто-схвалено у approved (Ollama недоступний)");
            }
        }
    }
}
