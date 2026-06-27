# PayPaste

Сервіс для збереження та поширення фрагментів коду і тексту у стилі ретро-сайтів 2000-х. Проєкт навчальний, але має робочі модулі монетизації, модерації, OAuth/Passkey та фонової обробки задач.

## Технічний стек

- **Backend:** PHP 8+, PDO, MySQL
- **Frontend:** Bootstrap 3, jQuery, Fetch API, vanilla JS
- **Auth:** Email/пароль, GitHub OAuth, Telegram Login Widget, WebAuthn/Passkey (FIDO2)
- **Integrations:** Resend (email), Donatello (fiat-платежі), Telegram Stars (XTR), OpenAI Moderation API, Ollama Cloud (AI-переписування)
- **Queue:** MySQL-backed job queue з retry, backoff та fallback

## Ключові можливості

- **Пасти:** публічні, приватні та платні (з вартістю перегляду)
- **Монетизація:** кредити, списання/нарахування, історія транзакцій з ідемпотентністю
- **Поповнення:** Donatello (UAH), Telegram Stars (XTR)
- **Рекламний квест:** безкоштовний доступ до платних паст через перегляд реклами
- **TTL та cleanup:** автоматичне видалення протермінованих паст та старих задач
- **AI-модерація:** локальна перевірка + OpenAI Moderation API + Ollama переписування
- **Черга задач:** фоновий worker (`cron/worker.php`) з retry/backoff/dead-обробкою
- **Адмін-панель:** CRUD паст/користувачів, транзакції, моніторинг черги
- **REST API:** JWT-автентифікація, CRUD паст, платні запити (1 кредит/запит)
- **Теми:** 6 кольорових тем (Retro, Dark, Terminal, Light, GitHub Orange, Retro Green)

## Швидкий старт

### Вимоги

- PHP 8.0+
- MySQL 8.0+ (або сумісна MariaDB)
- Apache з `mod_rewrite` або Nginx
- PHP extensions: `pdo_mysql`, `openssl`, `curl`, `mbstring`

### Встановлення

```bash
# 1. Клонувати
git clone https://github.com/dimdevoff/paypaste.git
cd paypaste

# 2. Імпортувати схему БД
mysql -u root -p < config/paypaste.sql

# 3. Конфігурація
cp config/config.example.php config/config.php
# Відредагувати config/config.php: БД, APP_URL, API-ключі, токени

# 4. Налаштувати веб-сервер (DocumentRoot → корінь проєкту)
# Для Nginx див. ops/nginx.example.conf
```

## Черга задач і worker

Фонова обробка через MySQL-таблицю `jobs` та уніфікований worker.

```bash
# Одноразовий запуск
php cron/worker.php

# Daemon-режим (для VPS)
php cron/worker.php --daemon

# Лише конкретний тип задач
php cron/worker.php --type=moderation_rewrite
php cron/worker.php --type=email_verify
```

Приклад systemd-сервісу: `ops/worker/paypaste-worker.service`

## Cleanup та TTL

- `cron/cleanup.php` — видалення протермінованих паст та старих dead/completed задач
- Lazy expiration — видалення при спробі доступу до простроченої пасти
- Inline queue fallback — ймовірнісна обробка задач під час HTTP-запиту (контролюється `QUEUE_INLINE_PROBABILITY`)

Приклад cron:

```bash
* * * * * php /path/to/project/cron/worker.php >> /path/to/project/data/logs/worker.log 2>&1
0 * * * * php /path/to/project/cron/cleanup.php --force >> /path/to/project/data/logs/cleanup.log 2>&1
```

## Безпека та anti-abuse

- **CSRF:** токени у всіх POST-формах, регенерація після успішної перевірки
- **Rate limiting:** комбінований ключ (дія + user/session fingerprint + браузерний сигнал + нормалізований IP). IP — лише м'який velocity-ліміт для NAT/shared networks
- **Фінансові інваріанти:** PDO-транзакції з `FOR UPDATE`, умовне списання (`WHERE credits >= ?`), `idempotency_key` у транзакціях
- **Файли:** захист `data/uploads/` через `.htaccess`/Nginx deny, проксі-завантаження через `api/download.php`
- **JWT:** HS256, 7 днів термін дії, списання 1 кредиту за запит

## Структура проєкту

```text
admin/                  # Адмін-панель (dashboard, CRUD, транзакції, черга)
api/                    # REST API, OAuth, WebAuthn, webhooks
  auth_token.php        # JWT-токени
  pastes.php            # CRUD паст (GET/POST/DELETE)
  oauth.php             # GitHub + Telegram OAuth
  passkey.php           # WebAuthn/FIDO2 реєстрація/вхід
  webhooks/             # Donatello, Telegram Stars, verify_ad
assets/                 # CSS, JS, зображення
  css/style.css         # 6 тем оформлення
  js/app.js             # Копіювання пасти, рекламний квест
  js/passkey.js         # WebAuthn клієнт
  js/theme-switch.js    # Миттєвий перемикач тем
config/                 # Конфігурація та SQL-схема
  config.php            # Константи (ключі, токени, URL)
  config.example.php    # Шаблон конфігурації
  db.php                # Singleton PDO
  paypaste.sql          # Повна схема БД (10 таблиць)
  bad_words.json        # Локальний фільтр модерації
cron/                   # Фонові скрипти
  worker.php            # Уніфікований worker (всі типи задач)
  cleanup.php           # Очищення протермінованих паст та задач
data/                   # Файлове сховище
  uploads/              # Вкладення паст (захищено .htaccess)
  logs/                 # Логи worker-а
docs/                   # Документація (architecture, API, шаблони, тощо)
includes/
  bootstrap.php         # Ініціалізація: сесії, БД, CSRF, getCurrentUser()
  controllers/          # AuthController, PasteController, SettingsController, VerifyController
  models/               # User, Paste, Order, Transaction, Passkey
  services/             # AuthService, CreditService, PasteService, AdQuestService
  Queue.php             # MySQL-backed черга задач
  Moderation.php        # Локальна + OpenAI модерація, Ollama переписування
  RateLimiter.php       # Комбінований rate limiting
  jwt.php               # HS256 JWT
  csrf.php              # CSRF + Remember Me (HMAC-cookie)
  mailer.php            # Resend API (enqueue + direct)
  webauthn.php          # CBOR-декодер, верифікація attestation/assertion
templates/              # PHP-шаблони (header, footer, home, create, view, login, settings, verify, credits)
```

## Документація

Детальна документація знаходиться у папці `docs/`:

| Документ | Зміст |
|----------|-------|
| [docs/architecture.md](docs/architecture.md) | Загальний огляд, архітектура MVC, потік даних, безпека, економіка |
| [docs/config.md](docs/config.md) | Конфігурація, константи, схема БД (10 таблиць), ER-діаграма |
| [docs/includes.md](docs/includes.md) | Моделі, сервіси, контролери, утиліти |
| [docs/api.md](docs/api.md) | REST API, JWT, OAuth, WebAuthn, webhooks |
| [docs/templates.md](docs/templates.md) | Шаблони, UI, форми, entry points |
| [docs/admin-cron-data.md](docs/admin-cron-data.md) | Адмін-панель, worker, cleanup, файлове сховище |
| [docs/assets.md](docs/assets.md) | CSS (6 тем), JS, статика |

## Ліцензія

Навчальний проєкт. Всі права належать авторам.
