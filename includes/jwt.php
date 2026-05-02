<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Проста реалізація JWT (JSON Web Token) для PHP без зовнішніх залежностей.
 * Використовує алгоритм HS256.
 */
class JWT {
    private static $secret = COOKIE_SECRET;

    /**
     * Створення токена
     */
    public static function encode($payload) {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Перевірка та декодування токена
     */
    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($header, $payload, $signature) = $parts;
        
        $validSignature = self::base64UrlEncode(hash_hmac('sha256', $header . "." . $payload, self::$secret, true));
        
        if ($signature !== $validSignature) {
            return null;
        }
        
        $payloadData = json_decode(self::base64UrlDecode($payload), true);
        
        // Перевірка терміну дії (exp), якщо він є
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return null;
        }
        
        return $payloadData;
    }

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
