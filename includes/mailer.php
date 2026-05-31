<?php
/**
 * Клас Mailer для відправки email через Resend API.
 *
 * Два режими роботи:
 *   - sendVerificationEmail() / sendEmailChangedNotification() : синхронні (з rate limiting),
 *     використовуються для fallback-сценаріїв коли черга недоступна
 *   - sendDirect() : пряма відправка без rate limiting, використовується worker-ом
 *   - enqueue*()   : постановка відправки у чергу (рекомендований спосіб)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/RateLimiter.php';

class Mailer {
    
    /**
     * Пряма відправка email через Resend API (без rate limiting).
     * Використовується worker-ом для обробки задач з черги.
     *
     * @param string $to      Email отримувача
     * @param string $subject Тема листа
     * @param string $html    HTML-зміст
     * @return bool Успішність відправки
     * @throws \RuntimeException При помилці API
     */
    public static function sendDirect(string $to, string $subject, string $html): bool {
        if (!defined('RESEND_API_KEY') || empty(RESEND_API_KEY)) {
            throw new \RuntimeException("RESEND_API_KEY не налаштовано.");
        }

        $apiKey = RESEND_API_KEY;
        $payload = json_encode([
            'from'    => MAIL_FROM,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 30, // Таймаут 30 секунд
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . RESEND_API_KEY,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException("Resend cURL помилка: $curlError");
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Resend API повернув HTTP $status: $response");
        }

        return true;
    }

    /**
     * Постановка верифікаційного email у чергу.
     * Замість синхронної відправки — ставить задачу для worker-а.
     *
     * @param string $to   Email отримувача
     * @param string $code Код верифікації
     * @return array Результат ['success' => bool, 'job_id' => string|null, 'error' => string|null]
     */
    public static function enqueueVerificationEmail(string $to, string $code): array {
        // Перевірка cooldown 180 секунд (захист від флуду навіть при черзі)
        if (!RateLimiter::check('email_cooldown:' . $to, 1, 180)) {
            return ['success' => false, 'job_id' => null, 'error' => 'Зачекайте 180 секунд перед наступною відправкою.'];
        }

        // Перевірка денного ліміту (3 рази на день)
        if (!RateLimiter::check('email_daily:' . $to, 3, 86400)) {
            return ['success' => false, 'job_id' => null, 'error' => 'Досягнуто денний ліміт (3 відправки). Спробуйте завтра.'];
        }

        // Ідемпотентний ключ: тип + email + код — запобігає дублюванню при повторних запитах
        $idempotencyKey = 'email_verify:' . $to . ':' . $code;

        require_once __DIR__ . '/Queue.php';
        $jobId = Queue::push(
            Queue::TYPE_EMAIL_VERIFY,
            ['to' => $to, 'code' => $code],
            $idempotencyKey
        );

        if ($jobId === null) {
            // Дублікат — це ок, лист вже в черзі
            return ['success' => true, 'job_id' => null, 'error' => null];
        }

        return ['success' => true, 'job_id' => $jobId, 'error' => null];
    }

    /**
     * Постановка повідомлення про зміну email у чергу.
     *
     * @param string $oldEmail Старий email
     * @param string $newEmail Новий email
     * @return array Результат
     */
    public static function enqueueEmailChangedNotification(string $oldEmail, string $newEmail): array {
        $idempotencyKey = 'email_changed:' . $oldEmail . ':' . $newEmail . ':' . time();

        require_once __DIR__ . '/Queue.php';
        $jobId = Queue::push(
            Queue::TYPE_EMAIL_CHANGED,
            ['old_email' => $oldEmail, 'new_email' => $newEmail],
            $idempotencyKey
        );

        return ['success' => $jobId !== null || $jobId === null, 'job_id' => $jobId];
    }

    /**
     * Синхронна відправка коду верифікації (fallback, якщо черга недоступна).
     * НЕ РЕКОМЕНДУЄТЬСЯ для основного потоку — використовуйте enqueueVerificationEmail().
     *
     * @param string $to   Email
     * @param string $code Код верифікації
     * @return array
     */
    public static function sendVerificationEmail(string $to, string $code): array {
        // Перевірка cooldown 180 секунд
        if (!RateLimiter::check('email_cooldown:' . $to, 1, 180)) {
            return ['success' => false, 'error' => 'Зачекайте 180 секунд перед наступною відправкою.'];
        }

        // Перевірка денного ліміту (3 рази на день)
        if (!RateLimiter::check('email_daily:' . $to, 3, 86400)) {
            return ['success' => false, 'error' => 'Досягнуто денний ліміт (3 відправки). Спробуйте завтра.'];
        }

        $template = file_get_contents(__DIR__ . '/../templates/email_verify.html');
        $html = str_replace('{{CODE}}', htmlspecialchars($code), $template);

        try {
            $success = self::sendDirect($to, 'Підтвердження пошти — PayPaste', $html);
            return ['success' => $success];
        } catch (\RuntimeException $e) {
            return ['success' => false, 'error' => 'Помилка відправки листа: ' . $e->getMessage()];
        }
    }

    /**
     * Синхронна відправка повідомлення про зміну email (fallback).
     *
     * @param string $oldEmail Старий email
     * @param string $newEmail Новий email
     * @return bool
     */
    public static function sendEmailChangedNotification(string $oldEmail, string $newEmail): bool {
        $template = file_get_contents(__DIR__ . '/../templates/email_changed.html');
        $html = str_replace('{{NEW_EMAIL}}', htmlspecialchars($newEmail), $template);

        try {
            return self::sendDirect($oldEmail, 'Зміна email-адреси — PayPaste', $html);
        } catch (\RuntimeException $e) {
            error_log("Mailer::sendEmailChangedNotification помилка: " . $e->getMessage());
            return false;
        }
    }
}
