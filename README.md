# PayPaste

Сервіс для збереження фрагментів коду і тексту у стилі ретро-сайтів 2000-х. Проєкт навчальний, але має робочі модулі монетизації, модерації, OAuth/Passkey та фонової обробки задач.

## Технічний стек

- Backend: PHP 8+, PDO, MySQL
- Frontend: Bootstrap 3, jQuery, Fetch API
- Auth: Email/пароль, GitHub OAuth, Telegram OAuth, WebAuthn/Passkey
- Integrations: Resend, Donatello, Telegram Stars, OpenAI Moderation, Ollama

## Ключові можливості

- Публічні, приватні та платні пасти
- Кредити, списання/нарахування, історія транзакцій
- TTL паст та cleanup
- Локальна + зовнішня AI-модерація
- AI-переписування контенту у фоновому режимі
- MySQL-backed черга задач (`jobs`) з retry/backoff
- Репозиторії, сервіси та command-обгортки для бізнес-логіки в `includes/`
- Адмін-панель

## Встановлення

### Вимоги

- PHP 8.0+
- MySQL 8.0+ (або сумісна MariaDB)
- Apache/Nginx
- PHP extensions: `pdo_mysql`, `openssl`, `curl`, `mbstring`

### Кроки

1. Клонувати репозиторій:
```bash
git clone https://github.com/dimdevoff/paypaste.git
cd paypaste
```

2. Імпортувати схему:
```bash
mysql -u root -p < config/paypaste.sql
```

3. Створити локальний конфіг:
```bash
cp config/config.example.php config/config.php
```
Заповнити `config/config.php` своїми ключами, URL та параметрами БД.

4. Налаштувати веб-сервер на корінь проєкту.

## Черга задач і worker

Система використовує таблицю `jobs` і обробник `cron/worker.php`.

### Одноразовий запуск

```bash
php cron/worker.php
```

### Безперервний режим

```bash
php cron/worker.php --daemon
```

### Лише певний тип задач

```bash
php cron/worker.php --type=moderation_rewrite
php cron/worker.php --type=email_verify
```

## Cleanup і TTL

- `cron/cleanup.php` видаляє протерміновані пасти
- той самий скрипт чистить старі завершені/dead задачі черги
- є демонстраційні fallback-механізми (lazy expiration, inline queue fallback), вони навмисні й керуються конфігом

Приклад cron:

```bash
* * * * * php /path/to/project/cron/worker.php >> /path/to/project/data/logs/worker.log 2>&1
0 * * * * php /path/to/project/cron/cleanup.php --force >> /path/to/project/data/logs/cleanup.log 2>&1
```

## Фінансові інваріанти й anti-abuse

- Rate limiting використовує комбінований ключ: дія, user id або серверний session fingerprint, браузерний сигнал і нормалізований IP-фактор.
- IP не є єдиним ключем блокування; для NAT/shared networks застосовується лише м'який velocity-ліміт із більшим порогом.

## Структура

```text
admin/                  # адмінка
api/                    # API, OAuth, webhooks
assets/                 # css/js/img
config/                 # config.php, config.example.php, SQL schema
cron/                   # worker, cleanup, wrappers
includes/
  controllers/          # Auth/Paste/Settings/Verify
  models/               # User/Paste/Order/Transaction/Passkey
  repositories/         # Repo layer для SQL-запитів
  services/             # бізнес-логіка (credits, paste, auth)
  Queue.php             # MySQL queue
  Moderation.php        # local + external moderation
  mailer.php            # enqueue/direct email
  HttpClient.php        # HTTP helper
  JobHandlers.php       # handlers для черги
  security_headers.php  # security headers
templates/              # html шаблони
data/uploads/           # файли користувачів
```
