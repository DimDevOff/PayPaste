<?php
/**
 * Централізоване встановлення HTTP security headers.
 *
 * Застосовується до всіх відповідей додатку через bootstrap.php.
 * Надає базовий захист від XSS, clickjacking, MIME-sniffing та інших векторів.
 *
 * CSP налаштована з урахуванням легітимного фронтенду:
 *  - CDN: Bootstrap, jQuery, highlight.js, shields.io
 *  - Adsterra: рекламні скрипти (iframe + JS)
 *  - Inline scripts/styles: необхідні для Bootstrap та Adsterra інтеграції
 *  - Telegram Login Widget
 */

if (!defined('SECURITY_HEADERS_SENT')) {
    define('SECURITY_HEADERS_SENT', true);

    // ─── X-Content-Type-Options ───────────────────────────────────────────
    // Блокує MIME-sniffing: браузер має використовувати лише Content-Type з сервера
    header('X-Content-Type-Options: nosniff');

    // ─── X-Frame-Options ───────────────────────────────────────────────────
    // Захист від clickjacking: сторінку не можна вбудувати в iframe
    // SAMEORIGIN дозволяє вкладання лише на тому ж домені
    header('X-Frame-Options: SAMEORIGIN');

    // ─── Referrer-Policy ──────────────────────────────────────────────────
    // Сувора політика: передає referral лише на той же origin
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // ─── Strict-Transport-Security (HSTS) ─────────────────────────────────
    // Примусове використання HTTPS протягом 1 року
    // includeSubDomains — захист піддоменів
    // Вмикається лише коли запит прийшов через HTTPS
    if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) === 'on' || $_SERVER['HTTPS'] === '1')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // ─── Permissions-Policy ────────────────────────────────────────────────
    // Обмеження доступу до браузерних API (камера, мікрофон, геолокація тощо)
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

    // ─── Content-Security-Policy ───────────────────────────────────────────
    // Головний захист від XSS та впровадження шкідливого контенту
    //
    // При побудові CSP враховані:
    //  - CDN скрипти (Bootstrap, jQuery, highlight.js)
    //  - Adsterra рекламні скрипти (динамічні піддомени, iframe)
    //  - Inline scripts (необхідні для atOptions, hljs, Bootstrap JS)
    //  - Inline styles (використовуються по всьому додатку)
    //  - Зображення з зовнішніх джерел (shields.io, CDN)
    //
    // nonce генерується на кожен запит і додається до дозволених inline-скриптів

    $nonce = base64_encode(random_bytes(16));

    // Зберігаємо nonce у глобальну константу для використання у шаблонах
    if (!defined('CSP_NONCE')) {
        define('CSP_NONCE', $nonce);
    }

    // Зберігаємо nonce у сесію для доступу з шаблонів
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['csp_nonce'] = $nonce;
    }

    $csp = [
        "default-src 'self'",
        // Скрипти: strict-dynamic + nonce — основний механізм CSP3.
        // 'strict-dynamic' дозволяє довіреному скрипту (з nonce) динамічно
        // підвантажувати інші скрипти (Adsterra, Telegram Widget тощо).
        // 'unsafe-inline' + https:/http: — fallback для CSP2-браузерів,
        // де nonce/strict-dynamic не підтримуються.
        // У CSP3-браузерах 'unsafe-inline' та URL-списки ігноруються.
        "script-src 'strict-dynamic' 'nonce-{$nonce}' 'unsafe-inline'"
            . " https: http:",
        // Скрипти, що завантажуються через <script src> без динаміки:
        // CSP3-браузери дозволяють їх через strict-dynamic (бо nonce-скрипт їх ініціює),
        // але для CSP2 fallback додано явні домени
        // (вони також у script-src-elem як fallback-директива)
        "script-src-elem 'strict-dynamic' 'nonce-{$nonce}' 'unsafe-inline'"
            . " https://code.jquery.com"
            . " https://maxcdn.bootstrapcdn.com"
            . " https://cdnjs.cloudflare.com"
            . " https://telegram.org",
        // Стилі: self + CDN + inline (Bootstrap theme, Adsterra iframe стилі)
        "style-src 'self' 'unsafe-inline'"
            . " https://maxcdn.bootstrapcdn.com"
            . " https://cdnjs.cloudflare.com",
        // Зображення: self + data: + зовнішні бейджі та CDN
        "img-src 'self' data: https:"
            . " https://img.shields.io"
            . " https://www.w3.org",
        // Шрифти: self + CDN
        "font-src 'self' https://maxcdn.bootstrapcdn.com",
        // З'єднання (fetch, XMLHttpRequest): self + рекламна мережа (Adsterra)
        "connect-src 'self' https:",
        // Frame: self (для Adsterra iframe-реклами)
        "frame-src 'self'"
            . " https://www.profitablecpmratenetwork.com"
            . " https://www.highperformanceformat.com"
            . " https://pl29310816.profitablecpmratenetwork.com",
        // Object: заборонено (Flash і подібні)
        "object-src 'none'",
        // Base URI: обмежено self (захист від base tag hijacking)
        "base-uri 'self'",
        // Form action: self + GitHub OAuth (редирект через форму)
        "form-action 'self' https://github.com",
        // Frame ancestors: self (дублює X-Frame-Options для CSP-сумісних браузерів)
        "frame-ancestors 'self'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp));
}

/**
 * Хелпер: повертає CSP nonce для використання у шаблонах.
 * Використання: <script nonce="<?= csp_nonce() ?>">...</script>
 *
 * @return string
 */
function csp_nonce(): string {
    return CSP_NONCE;
}
