<?php
/**
 * Централізоване встановлення HTTP security headers.
 *
 * Застосовується до всіх відповідей додатку через bootstrap.php.
 * Надає базовий захист від XSS, clickjacking, MIME-sniffing та інших векторів.
 *
 * CSP налаштована з урахуванням легітимного фронтенду:
 *  - CDN: Bootstrap, jQuery, highlight.js, shields.io
 *  - Adsterra: рекламні скрипти (iframe + JS, eval для invoke.js)
 *  - Inline scripts/styles: необхідні для Bootstrap та Adsterra інтеграції
 *  - Telegram Login Widget (iframe oauth.telegram.org)
 *  - Cloudflare analytics (static.cloudflareinsights.com)
 */

if (!defined('SECURITY_HEADERS_SENT')) {
    define('SECURITY_HEADERS_SENT', true);

    // ─── X-Content-Type-Options ───────────────────────────────────────────
    header('X-Content-Type-Options: nosniff');

    // ─── X-Frame-Options ───────────────────────────────────────────────────
    header('X-Frame-Options: SAMEORIGIN');

    // ─── Referrer-Policy ──────────────────────────────────────────────────
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // ─── Strict-Transport-Security (HSTS) ─────────────────────────────────
    if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) === 'on' || $_SERVER['HTTPS'] === '1')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // ─── Permissions-Policy ────────────────────────────────────────────────
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    // ─── Content-Security-Policy ───────────────────────────────────────────
    $nonce = base64_encode(random_bytes(16));

    if (!defined('CSP_NONCE')) {
        define('CSP_NONCE', $nonce);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['csp_nonce'] = $nonce;
    }

    $csp = [
        "default-src 'self'",

        // Скрипти: strict-dynamic + nonce для безпеки,
        // unsafe-eval потрібен для Adsterra invoke.js (використовує eval),
        // https: дозволяє будь-які зовнішні скрипти через HTTPS
        "script-src 'strict-dynamic' 'nonce-{$nonce}' 'unsafe-inline' 'unsafe-eval' https: http:",

        // script-src-elem: https: дозволяє будь-які зовнішні скрипти (Adsterra, CDN тощо)
        "script-src-elem 'strict-dynamic' 'nonce-{$nonce}' 'unsafe-inline' 'unsafe-eval' https:",

        // Стилі
        "style-src 'self' 'unsafe-inline'"
            . " https://maxcdn.bootstrapcdn.com"
            . " https://cdnjs.cloudflare.com",

        // Зображення
        "img-src 'self' data: https:"
            . " https://img.shields.io"
            . " https://www.w3.org",

        // Шрифти
        "font-src 'self' https://maxcdn.bootstrapcdn.com",

        // З'єднання: self + Adsterra трекінг + Cloudflare
        "connect-src 'self' https:",

        // Фрейми: self + Telegram OAuth + Adsterra + Cloudflare
        "frame-src 'self'"
            . " https://oauth.telegram.org"
            . " https://www.profitablecpmratenetwork.com"
            . " https://www.highperformanceformat.com"
            . " https://pl29310816.profitablecpmratenetwork.com"
            . " https://pl29310817.profitablecpmratenetwork.com",

        // Медіа: дозволено data: для рекламних банерів
        "media-src 'self' data: https:",

        // Object: заборонено
        "object-src 'none'",

        // Base URI
        "base-uri 'self'",

        // Form action: self + GitHub OAuth
        "form-action 'self' https://github.com",

        // Frame ancestors
        "frame-ancestors 'self'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp));
}

/**
 * Хелпер: повертає CSP nonce для використання у шаблонах.
 */
function csp_nonce(): string {
    return CSP_NONCE;
}
