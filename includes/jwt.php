<?php
require_once __DIR__ . '/../config/config.php';

/**
 * JWT (JSON Web Token) реалізація на алгоритмі RS256.
 * Використовує асиметричні RSA-ключі замість спільного секрету.
 *
 * Приватний ключ — тільки для підпису (на сервері).
 * Публічний ключ — для верифікації (можна поширювати).
 */
class JWT {
    /** @var string|null Кешований приватний ключ */
    private static $privateKey = null;
    /** @var string|null Кешований публічний ключ */
    private static $publicKey = null;

    /**
     * Завантажує приватний ключ із файлу або рядка.
     */
    private static function getPrivateKey(): string {
        if (self::$privateKey !== null) {
            return self::$privateKey;
        }

        $key = JWT_PRIVATE_KEY;

        // Якщо ключ — шлях до файлу (file://)
        if (str_starts_with($key, 'file://')) {
            $path = substr($key, 7);
            if (!file_exists($path)) {
                throw new \RuntimeException('JWT: приватний ключ не знайдено: ' . $path);
            }
            $key = file_get_contents($path);
        }

        if (empty($key)) {
            throw new \RuntimeException('JWT: JWT_PRIVATE_KEY не налаштовано');
        }

        self::$privateKey = $key;
        return $key;
    }

    /**
     * Завантажує публічний ключ із файлу або рядка.
     */
    private static function getPublicKey(): string {
        if (self::$publicKey !== null) {
            return self::$publicKey;
        }

        $key = JWT_PUBLIC_KEY;

        // Якщо ключ — шлях до файлу (file://)
        if (str_starts_with($key, 'file://')) {
            $path = substr($key, 7);
            if (!file_exists($path)) {
                throw new \RuntimeException('JWT: публічний ключ не знайдено: ' . $path);
            }
            $key = file_get_contents($path);
        }

        if (empty($key)) {
            throw new \RuntimeException('JWT: JWT_PUBLIC_KEY не налаштовано');
        }

        self::$publicKey = $key;
        return $key;
    }

    /**
     * Створення JWT-токена (RS256).
     *
     * @param array $payload Дані токена (sub, iat, exp, ...)
     * @return string Підписаний JWT-токен
     */
    public static function encode($payload) {
        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]);

        $base64UrlHeader  = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        $signingInput = $base64UrlHeader . '.' . $base64UrlPayload;

        $privateKey = self::getPrivateKey();
        $signature = '';
        $success = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new \RuntimeException('JWT: не вдалося підписати токен. ' . openssl_error_string());
        }

        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    /**
     * Перевірка та декодування JWT-токена.
     *
     * @param string $token JWT-токен
     * @return array|null Декодований payload або null при невдачі
     */
    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($headerB64, $payloadB64, $signatureB64) = $parts;

        // Декодуємо заголовок для перевірки алгоритму
        $headerJson = self::base64UrlDecode($headerB64);
        if ($headerJson === false) {
            return null;
        }
        $header = json_decode($headerJson, true);
        if (!$header || ($header['alg'] ?? '') !== 'RS256') {
            return null;
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $signature = self::base64UrlDecode($signatureB64);
        if ($signature === false) {
            return null;
        }

        $publicKey = self::getPublicKey();
        $valid = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($valid !== 1) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadB64);
        if ($payloadJson === false) {
            return null;
        }

        $payloadData = json_decode($payloadJson, true);
        if (!$payloadData) {
            return null;
        }

        // Перевірка терміну дії (exp)
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return null;
        }

        // Перевірка «не раніше» (nbf)
        if (isset($payloadData['nbf']) && $payloadData['nbf'] > time()) {
            return null;
        }

        return $payloadData;
    }

    // ═══════════════════════════════════════════
    // Base64url helpers
    // ═══════════════════════════════════════════

    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
