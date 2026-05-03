<?php

// Database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'paypaste');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application URL
define('APP_URL', 'https://yourdomain.com');

// Telegram Bots
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN'); // @YOURBOTNAME
define('TELEGRAM_LOGIN_BOT_TOKEN', 'YOUR_LOGIN_BOT_TOKEN'); // @YOURBOTNAME

// Donatello
define('DONATELLO_TOKEN', 'YOUR_DONATELLO_TOKEN');
define('DONATELLO_URL', 'https://donatello.to/yourpage');

// GitHub OAuth
define('GITHUB_CLIENT_ID', 'YOUR_GITHUB_CLIENT_ID');
define('GITHUB_CLIENT_SECRET', 'YOUR_GITHUB_CLIENT_SECRET');

// WebAuthn / Passkey
define('WEBAUTHN_RP_ID', 'yourdomain.com');
define('WEBAUTHN_ORIGIN', 'https://yourdomain.com');

// Cookie Secret
define('COOKIE_SECRET', 'YOUR_SECRET_KEY');

// Adsterra — рекламна мережа
define('ADSTERRA_SOCIAL_BAR_URL',  'https://YOUR_SOCIALBAR_SCRIPT_URL.js');
define('ADSTERRA_POPUNDER_URL',    'https://YOUR_POPUNDER_SCRIPT_URL.js');
define('ADSTERRA_SMARTLINK_URL',   'https://YOUR_SMARTLINK_URL');
define('ADSTERRA_160x300_KEY',     'YOUR_160x300_KEY');
define('ADSTERRA_320x50_KEY',      'YOUR_320x50_KEY');
define('ADSTERRA_300x250_KEY',     'YOUR_300x250_KEY');
define('ADSTERRA_INVOKE_BASE_URL', 'https://www.highperformanceformat.com');

// AI Moderation & Rewriting
define('OPENAI_API_KEY', 'YOUR_OPENAI_API_KEY');
define('OLLAMA_API_URL', 'https://ollama.com/api');
define('OLLAMA_API_KEY', 'YOUR_OLLAMA_API_KEY');
define('OLLAMA_MODEL', 'gemma4:31b-cloud');
