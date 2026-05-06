<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/RateLimiter.php';

class Mailer {
    
    /**
     * Відправляє email через Resend API
     */
    private static function send(string $to, string $subject, string $html): bool {
        if (!defined('RESEND_API_KEY') || empty(RESEND_API_KEY)) {
            error_log("RESEND_API_KEY is not configured.");
            return false;
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
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            error_log("Resend API Error ($status): " . $response);
            $_SESSION['mailer_last_error'] = $response; // Тимчасово для дебагу
            return false;
        }

        return true;
    }

    /**
     * Відправляє код верифікації
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

        $success = self::send($to, 'Підтвердження пошти — PayPaste', $html);
 
        if ($success) {
            return ['success' => true];
        } else {
            $apiError = '';
            if (isset($_SESSION['mailer_last_error'])) {
                $errData = json_decode($_SESSION['mailer_last_error'], true);
                $apiError = ' (Resend: ' . ($errData['message'] ?? 'Unknown Error') . ')';
                unset($_SESSION['mailer_last_error']);
            }
            return ['success' => false, 'error' => 'Помилка відправки листа на сервері.' . $apiError];
        }
    }

    /**
     * Відправляє повідомлення на старий email про його зміну
     */
    public static function sendEmailChangedNotification(string $oldEmail, string $newEmail): bool {
        $template = file_get_contents(__DIR__ . '/../templates/email_changed.html');
        $html = str_replace('{{NEW_EMAIL}}', htmlspecialchars($newEmail), $template);

        return self::send($oldEmail, 'Зміна email-адреси — PayPaste', $html);
    }
}
