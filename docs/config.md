# 📁 config/ — Конфігурація проекту PayPaste

> Ця папка містить усі налаштування проекту: підключення до БД, секретні ключі, схему бази даних та файли модерації.
>
> **Для новачка:** `config.php` — це єдине місце де зберігаються усі секрети (паролі БД, API-ключі). Ніколи не комітьте `config.php` — тільки `config.example.php` з плейсхолдерами. `paypaste.sql` — це повне SQL-створення бази даних, можна імпортувати в MySQL для початку роботи.

---

## Зміст

1. [Огляд папки](#огляд-папки)
2. [config.php — Основні налаштування](#configphp--основні-налаштування)
3. [config.example.php — Шаблон конфігурації](#configexamplephp--шаблон-конфігурації)
4. [db.php — Підключення до БД (Singleton PDO)](#dbphp--підключення-до-бд-singleton-pdo)
5. [paypaste.sql — Схема бази даних](#paypastesql--схема-бази-даних)
6. [bad_words.json / bad_words.example.json — Фільтр модерації](#bad_wordsjson--bad_wordsexamplejson--фільтр-модерації)
7. [Взаємозв'язки з іншими папками](#взаємозв'язки-з-іншими-папками)

---

## Огляд папки

| Файл                     | Призначення                                          |
| ------------------------ | ---------------------------------------------------- |
| `config.php`             | Робочий файл конфігурації з ключами                  |
| `config.example.php`     | Шаблон конфігурації з плейсхолдерами для розгортання |
| `db.php`                 | Клас `DB` — Singleton PDO-з'єднання з MySQL          |
| `paypaste.sql`           | Повна SQL-схема бази даних `paypaste`                |
| `bad_words.json`         | Робочий список заборонених слів для модерації        |
| `bad_words.example.json` | Шаблон списку заборонених слів                       |

---

## config.php — Основні налаштування

Файл визначає усі константи проекту через `define()`. `config.example.php` використовується як шаблон для розгортання.

### Константи бази даних

| Константа | Тип    | Опис               | Приклад     |
| --------- | ------ | ------------------ | ----------- |
| `DB_HOST` | string | Хост MySQL-сервера | `127.0.0.1` |
| `DB_NAME` | string | Назва бази даних   | `paypaste`  |
| `DB_USER` | string | Користувач MySQL   | `root`      |
| `DB_PASS` | string | Пароль MySQL       | `""`        |

### Константи додатку

| Константа | Тип    | Опис                      | Приклад                  |
| --------- | ------ | ------------------------- | ------------------------ |
| `APP_URL` | string | Базова URL-адреса додатку | `https://yourdomain.com` |

### Telegram-боти

| Константа                  | Тип    | Опис                                                                                                |
| -------------------------- | ------ | --------------------------------------------------------------------------------------------------- |
| `TELEGRAM_BOT_TOKEN`       | string | Токен Telegram-бота для платежів (Telegram Stars) та сповіщень                                      |
| `TELEGRAM_LOGIN_BOT_TOKEN` | string | Токен окремого Telegram-бота для авторизації (Telegram Login Widget)                                |
| `TELEGRAM_WEBHOOK_SECRET`  | string | Секрет для верифікації webhook-запитів Telegram (передається через `secret_token` при `setWebhook`) |

### Платіжна система Donatello

| Константа         | Тип    | Опис                                                         |
| ----------------- | ------ | ------------------------------------------------------------ |
| `DONATELLO_TOKEN` | string | API-токен сервісу Donatello (для прийому донатів/поповнення) |
| `DONATELLO_URL`   | string | URL сторінки донату на Donatello                             |

### GitHub OAuth

| Константа              | Тип    | Опис                               |
| ---------------------- | ------ | ---------------------------------- |
| `GITHUB_CLIENT_ID`     | string | Client ID GitHub OAuth-додатку     |
| `GITHUB_CLIENT_SECRET` | string | Client Secret GitHub OAuth-додатку |

> 📌 Використовується для авторизації через GitHub. Обробник: `api/oauth.php`.

### WebAuthn / Passkey

| Константа         | Тип    | Опис                                        |
| ----------------- | ------ | ------------------------------------------- |
| `WEBAUTHN_RP_ID`  | string | Relying Party ID — домен сайту для WebAuthn |
| `WEBAUTHN_ORIGIN` | string | Повний Origin (з протоколом) для WebAuthn   |

> 📌 Значення `WEBAUTHN_RP_ID` має збігатися з доменом сайту (без протоколу). Наприклад: `yourdomain.com`.

### Безпека

| Константа       | Тип    | Опис                                                                       |
| --------------- | ------ | -------------------------------------------------------------------------- |
| `COOKIE_SECRET` | string | Секретний ключ для підпису «Remember Me» cookie (див. `includes/csrf.php`) |

> ⚠️ Має бути довгим випадковим рядком. Використовується для HMAC-підпису cookie `remember_me`.

### Рекламна мережа Adsterra

| Константа                  | Тип    | Опис                                       |
| -------------------------- | ------ | ------------------------------------------ |
| `ADSTERRA_SOCIAL_BAR_URL`  | string | URL скрипта Social Bar (бічна панель)      |
| `ADSTERRA_POPUNDER_URL`    | string | URL скрипта Popunder (підкладне вікно)     |
| `ADSTERRA_SMARTLINK_URL`   | string | URL SmartLink (розумне перенаправлення)    |
| `ADSTERRA_160x300_KEY`     | string | Ключ банера 160×300px                      |
| `ADSTERRA_320x50_KEY`      | string | Ключ банера 320×50px (мобільний)           |
| `ADSTERRA_300x250_KEY`     | string | Ключ банера 300×250px                      |
| `ADSTERRA_INVOKE_BASE_URL` | string | Базовий URL для виклику рекламних скриптів |

> 📌 Використовуються у шаблонах (`templates/header.php`, `templates/paste_view.php`) для відображення реклами на платних пастах. **Публічні пасти не містять реклами.**

### AI Модерація та Переписування

| Константа        | Тип    | Опис                                                       |
| ---------------- | ------ | ---------------------------------------------------------- |
| `OPENAI_API_KEY` | string | API-ключ OpenAI (резервний провайдер)                      |
| `OLLAMA_API_URL` | string | URL API Ollama (основний провайдер AI)                     |
| `OLLAMA_API_KEY` | string | API-ключ Ollama                                            |
| `OLLAMA_MODEL`   | string | Назва моделі Ollama (за замовчуванням: `gemma4:31b-cloud`) |

> 📌 Використовується `cron/ai_worker.php` та `includes/Moderation.php` для перевірки контенту та AI-переписування паст.

### Email (Resend)

| Константа        | Тип    | Опис                                                                            |
| ---------------- | ------ | ------------------------------------------------------------------------------- |
| `RESEND_API_KEY` | string | API-ключ сервісу Resend для відправки email                                     |
| `MAIL_FROM`      | string | Email-адреса відправника (`PasteBin <noreply@yourdomain.com>` за замовчуванням) |

> 📌 Використовується `includes/mailer.php` для верифікації email та сповіщень.

### Черга фонових задач

| Константа                  | Тип | Опис                                                         |
| -------------------------- | --- | ------------------------------------------------------------ |
| `QUEUE_INLINE_PROBABILITY` | int | Ймовірність (%) лінивої обробки черги при кожному веб-запиті |

Значення:

- `0` — вимкнено, рекомендовано для production з фоновим worker-ом
- `3` — легкий тимчасовий fallback, якщо worker недоступний
- `10` — агресивний fallback для середовищ без постійного worker-а

> 📌 Докладніше про чергу задач див. у [cron/ документації](admin-cron-data.md).

---

## config.example.php — Шаблон конфігурації

Файл-шаблон для розгортання на новому сервері. Містить усі ті ж константи, що й `config.php`, але з плейсхолдерами замість реальних значень:

- `YOUR_BOT_TOKEN` — замість токенів Telegram
- `YOUR_DONATELLO_TOKEN` — замість токена Donatello
- `YOUR_GITHUB_CLIENT_ID` / `YOUR_SECRET` — замість GitHub OAuth ключів
- `YOUR_SECRET_KEY` — замість COOKIE_SECRET
- `YOUR_OPENAI_API_KEY` — замість AI ключів
- `YOUR_RESEND_API_KEY` — замість Resend ключа

---

## db.php — Підключення до БД (Singleton PDO)

Файл реалізує патерн **Singleton** для єдиного PDO-з'єднання з MySQL.

### Клас `DB`

```php
class DB {
    private static $instance = null;  // Єдиний екземпляр
    private $pdo;                      // PDO-об'єкт

    private function __construct() { ... }  // Закритий конструктор
    public static function getInstance(): DB { ... }  // Отримання екземпляра
    public function getPDO(): PDO { ... }  // Отримання PDO-об'єкта
}
```

### Параметри підключення

| Параметр                       | Значення                                      | Опис                           |
| ------------------------------ | --------------------------------------------- | ------------------------------ |
| DSN                            | `mysql:host=$host;dbname=$db;charset=utf8mb4` | MySQL з utf8mb4                |
| `PDO::ATTR_ERRMODE`            | `PDO::ERRMODE_EXCEPTION`                      | Виключення замість попереджень |
| `PDO::ATTR_DEFAULT_FETCH_MODE` | `PDO::FETCH_ASSOC`                            | Асоціативні масиви             |
| `PDO::ATTR_EMULATE_PREPARES`   | `false`                                       | Справжні prepared statements   |

### Використання

```php
// В будь-якому файлі проекту:
$pdo = DB::getInstance()->getPDO();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
```

> ⚠️ При помилці підключення записує в `error_log` і викликає `die()` з загальним повідомленням (без деталей SQL).

---

## paypaste.sql — Схема бази даних

Повна SQL-схема бази даних `paypaste`. Кодування: **utf8mb4_unicode_ci**.

### ER-діаграма зв'язків

```
users ──1:N── pastes ──1:N── paste_tags
  │            │
  │            ├── unlocked_pastes (N:M з users)
  │            └── ad_events
  │
  ├──1:N── orders
  ├──1:N── transactions ────── (FK → pastes, orders)
  ├──1:N── passkeys
  └──1:N── rate_limits

jobs (незалежна таблиця черги)
```

### Таблиця `users` — Користувачі

| Колонка                   | Тип                  | Обмеження                    | Опис                                  |
| ------------------------- | -------------------- | ---------------------------- | ------------------------------------- |
| `id`                      | VARCHAR(50)          | **PRIMARY KEY**              | Унікальний ID користувача             |
| `email`                   | VARCHAR(255)         | **NOT NULL UNIQUE**          | Email-адреса (логін)                  |
| `telegram_id`             | BIGINT               | NULL UNIQUE                  | ID Telegram-акаунта (для OAuth)       |
| `github_id`               | VARCHAR(255)         | NULL UNIQUE                  | ID GitHub-акаунта (для OAuth)         |
| `nickname`                | VARCHAR(50)          | **NOT NULL**                 | Відображуване ім'я                    |
| `password_hash`           | VARCHAR(255)         | **NOT NULL**                 | Хеш пароля (bcrypt/argon2)            |
| `role`                    | ENUM('user','admin') | DEFAULT 'user'               | Роль користувача                      |
| `theme`                   | VARCHAR(20)          | **NOT NULL** DEFAULT 'retro' | Тема інтерфейсу                       |
| `api_key`                 | VARCHAR(64)          | UNIQUE NULL                  | API-ключ для зовнішнього доступу      |
| `credits`                 | INT                  | DEFAULT 100                  | Баланс кредитів (стартовий бонус 100) |
| `email_verified`          | TINYINT              | **NOT NULL** DEFAULT 0       | Чи верифіковано email                 |
| `verification_code`       | VARCHAR(6)           | NULL                         | OTP-код верифікації email             |
| `verification_expires_at` | DATETIME             | NULL                         | Час закінчення OTP-коду               |
| `created_at`              | TIMESTAMP            | DEFAULT CURRENT_TIMESTAMP    | Час реєстрації                        |

> 📌 **Стартовий бонус:** +100 кредитів при реєстрації (див. `credits DEFAULT 100`).  
> 📌 **OAuth:** Telegram та GitHub авторизація через `telegram_id` / `github_id`.  
> 📌 **API-ключ:** Генерується для доступу через REST API (`api/pastes.php`).

### Таблиця `pastes` — Пасти (фрагменти коду/тексту)

| Колонка              | Тип                                   | Обмеження                                    | Опис                                    |
| -------------------- | ------------------------------------- | -------------------------------------------- | --------------------------------------- |
| `id`                 | VARCHAR(50)                           | **PRIMARY KEY**                              | Унікальний ID пасти (random_bytes(8))   |
| `title`              | VARCHAR(255)                          | DEFAULT 'Без назви'                          | Заголовок                               |
| `content`            | LONGTEXT                              | **NOT NULL**                                 | Вміст пасти                             |
| `user_id`            | VARCHAR(50)                           | NULL, **FK → users.id** (ON DELETE SET NULL) | Автор                                   |
| `is_paid`            | BOOLEAN                               | DEFAULT FALSE                                | Чи є паста платною                      |
| `view_cost`          | INT                                   | DEFAULT 0                                    | Вартість доступу в кредитах             |
| `is_private`         | BOOLEAN                               | DEFAULT FALSE                                | Чи є паста приватною                    |
| `expires_at`         | TIMESTAMP                             | NULL                                         | Час закінчення TTL (NULL = нескінченно) |
| `is_pending_rewrite` | BOOLEAN                               | DEFAULT FALSE                                | Чи очікує AI-переписування              |
| `moderation_status`  | ENUM('pending','approved','rejected') | DEFAULT 'approved'                           | Статус модерації                        |
| `moderation_result`  | JSON                                  | NULL                                         | Категорії порушень при rejected         |
| `language`           | VARCHAR(50)                           | DEFAULT 'plaintext'                          | Мова/синтаксис підсвітки                |
| `created_at`         | TIMESTAMP                             | DEFAULT CURRENT_TIMESTAMP                    | Час створення                           |

**Індекси:**

- `idx_moderation_status` — на `moderation_status` для швидкого відбору паст на модерації

**Типи паст за доступом:**
| Тип | Умова | Доступ |
|-----|-------|--------|
| Публічна | `is_paid=0, is_private=0` | Усі (без реклами) |
| Приватна | `is_private=1` | Тільки автор |
| Платна | `is_paid=1` | Автор, покупці, або через рекламний квест |
| Протермінована | `expires_at < NOW()` | Ніхто (404) |

> 📌 **Ліниве видалення:** Протерміновані пасти видаляються при зверненні через `Paste::findById()`.  
> 📌 **Модерація:** Нові пасти можуть отримувати `moderation_status='pending'` і обробляються через `cron/ai_worker.php` або `includes/Moderation.php`.  
> 📌 **AI-переписування:** Якщо `is_pending_rewrite=TRUE`, паста стає в чергу на AI-переписування.

### Таблиця `unlocked_pastes` — Куплений доступ (N:M)

| Колонка       | Тип         | Обмеження                                  | Опис              |
| ------------- | ----------- | ------------------------------------------ | ----------------- |
| `user_id`     | VARCHAR(50) | **PK**, FK → users.id (ON DELETE CASCADE)  | Хто купив         |
| `paste_id`    | VARCHAR(50) | **PK**, FK → pastes.id (ON DELETE CASCADE) | Яку пасту         |
| `unlocked_at` | TIMESTAMP   | DEFAULT CURRENT_TIMESTAMP                  | Час розблокування |

> 📌 Складовий первинний ключ `(user_id, paste_id)` — один користувач може розблокувати одну пасту лише один раз.

### Таблиця `orders` — Замовлення на поповнення балансу

| Колонка                | Тип                                    | Обмеження                                       | Опис                                |
| ---------------------- | -------------------------------------- | ----------------------------------------------- | ----------------------------------- |
| `id`                   | VARCHAR(50)                            | **PRIMARY KEY**                                 | ID замовлення (напр. `order_12345`) |
| `user_id`              | VARCHAR(50)                            | **NOT NULL**, FK → users.id (ON DELETE CASCADE) | Хто замовив                         |
| `service`              | ENUM('donatello','tg_stars','unknown') | **NOT NULL** DEFAULT 'unknown'                  | Платіжний сервіс                    |
| `amount_credits`       | INT                                    | **NOT NULL**                                    | Кількість кредитів для зарахування  |
| `status`               | ENUM('pending','completed','canceled') | DEFAULT 'pending'                               | Статус замовлення                   |
| `external_provider_id` | VARCHAR(255)                           | NULL                                            | ID операції на стороні провайдера   |
| `created_at`           | TIMESTAMP                              | DEFAULT CURRENT_TIMESTAMP                       | Час створення                       |
| `updated_at`           | TIMESTAMP                              | DEFAULT CURRENT_TIMESTAMP **ON UPDATE**         | Час останнього оновлення            |

> 📌 Обробники webhook-ів: `api/webhooks/donatello.php` та `api/webhooks/telegram_stars.php`.  
> 📌 Статус `completed` встановлюється після підтвердження від платіжного сервісу.

### Таблиця `transactions` — Історія транзакцій

| Колонка            | Тип                                                                    | Обмеження                                       | Опис                          |
| ------------------ | ---------------------------------------------------------------------- | ----------------------------------------------- | ----------------------------- |
| `id`               | INT                                                                    | **AUTO_INCREMENT PK**                           | Внутрішній ID транзакції      |
| `user_id`          | VARCHAR(50)                                                            | **NOT NULL**, FK → users.id (ON DELETE CASCADE) | Користувач                    |
| `amount`           | INT                                                                    | **NOT NULL**                                    | Сума: +поповнення, −списання  |
| `type`             | ENUM('topup','purchase','sale','creation_fee','api_usage','ad_reward') | **NOT NULL**                                    | Тип транзакції                |
| `service`          | VARCHAR(50)                                                            | NULL                                            | Джерело (donatello, tg_stars) |
| `related_paste_id` | VARCHAR(50)                                                            | NULL, FK → pastes.id (ON DELETE SET NULL)       | Пов'язана паста               |
| `related_order_id` | VARCHAR(50)                                                            | NULL, FK → orders.id (ON DELETE SET NULL)       | Пов'язане замовлення          |
| `description`      | VARCHAR(255)                                                           | NULL                                            | Опис операції                 |
| `idempotency_key`  | VARCHAR(255)                                                           | NULL UNIQUE                                     | Захист від дублювання         |
| `created_at`       | TIMESTAMP                                                              | DEFAULT CURRENT_TIMESTAMP                       | Час транзакції                |

**Типи транзакцій:**

| Тип            | Опис                                                           | Знак amount |
| -------------- | -------------------------------------------------------------- | ----------- |
| `topup`        | Поповнення балансу через Donatello/Telegram Stars              | +           |
| `purchase`     | Купівля доступу до платної пасти                               | −           |
| `sale`         | Продаж своєї платної пасти (хтось купив)                       | +           |
| `creation_fee` | Комісія за створення платної пасти (`ceil(content_length/10)`) | −           |
| `api_usage`    | Плата за використання API                                      | −           |
| `ad_reward`    | Нагорода за перегляд реклами (квест)                           | +           |

> ⚠️ **Фінансові операції** обов'язково виконуються в **PDO Transaction** (`beginTransaction`, `commit`, `rollBack`).  
> 📌 `idempotency_key` гарантує, що повторна операція з тим самим ключем ігнорується.

### Таблиця `ad_events` — Рекламні події (серверний облік квестів)

| Колонка             | Тип         | Обмеження                                        | Опис                               |
| ------------------- | ----------- | ------------------------------------------------ | ---------------------------------- |
| `id`                | INT         | **AUTO_INCREMENT PK**                            | Внутрішній ID                      |
| `paste_id`          | VARCHAR(50) | **NOT NULL**, FK → pastes.id (ON DELETE CASCADE) | Паста, за яку дивляться рекламу    |
| `user_id`           | VARCHAR(50) | NULL                                             | ID користувача (NULL для анонімів) |
| `user_session_hash` | VARCHAR(64) | **NOT NULL**                                     | Хеш відбитка сесії+користувача     |
| `quest_id`          | VARCHAR(32) | **NOT NULL**                                     | ID квесту (рекламного завдання)    |
| `nonce`             | VARCHAR(32) | **NOT NULL**                                     | Одноразовий ідентифікатор події    |
| `step`              | TINYINT     | **NOT NULL**                                     | Номер зарахованої події (1–3)      |
| `accepted_at`       | TIMESTAMP   | DEFAULT CURRENT_TIMESTAMP                        | Час прийняття події                |

**Індекси:**

- `uk_ad_event_nonce` — UNIQUE на `(paste_id, user_session_hash, nonce)` — захист від дублювання
- `idx_ad_events_paste_user` — на `(paste_id, user_session_hash)`
- `idx_ad_events_quest` — на `quest_id`

> 📌 Замінює довіру до сесій при підрахунку прогресу рекламного квесту.  
> 📌 Анонімні користувачі ідентифікуються через `user_session_hash` замість `user_id`.  
> 📌 Квест складається з 3 кроків (3 реклами по 10 секунд кожна). Обробник: `api/webhooks/verify_ad.php`.

### Таблиця `passkeys` — WebAuthn/FIDO2 ключі

| Колонка          | Тип          | Обмеження                                       | Опис                                         |
| ---------------- | ------------ | ----------------------------------------------- | -------------------------------------------- |
| `id`             | VARCHAR(50)  | **PRIMARY KEY**                                 | Унікальний ID ключа                          |
| `user_id`        | VARCHAR(50)  | **NOT NULL**, FK → users.id (ON DELETE CASCADE) | Власник ключа                                |
| `credential_id`  | VARCHAR(255) | **NOT NULL UNIQUE**                             | ID credential від автентифікатора            |
| `public_key_pem` | TEXT         | **NOT NULL**                                    | Публічний ключ у форматі PEM                 |
| `counter`        | INT          | DEFAULT 0                                       | Лічильник підписів (anti-replay)             |
| `aaguid`         | VARCHAR(255) | NULL                                            | Authenticator Attestation GUID               |
| `transports`     | VARCHAR(255) | NULL                                            | Supported transports (usb, nfc, internal...) |
| `created_at`     | TIMESTAMP    | DEFAULT CURRENT_TIMESTAMP                       | Час реєстрації ключа                         |

> 📌 API-обробники: `api/passkey.php` (реєстрація/авторизація), `assets/js/passkey.js` (клієнт).  
> 📌 Утиліти: `includes/webauthn.php`.

### Таблиця `paste_tags` — Теги паст (N:M)

| Колонка    | Тип         | Обмеження                                  | Опис       |
| ---------- | ----------- | ------------------------------------------ | ---------- |
| `paste_id` | VARCHAR(50) | **PK**, FK → pastes.id (ON DELETE CASCADE) | Паста      |
| `tag`      | VARCHAR(50) | **PK**                                     | Назва тегу |

**Індекси:**

- `idx_tag` — на `tag` для швидкого пошуку за тегом

> 📌 Теги додаються вручну через поле введення при створенні пасти.  
> 📌 Колір тегу генерується з MD5-хешу через `Paste::getTagColor()`.  
> 📌 Фільтрація паст за тегом підтримується на головній сторінці.

### Таблиця `rate_limits` — Ліміти запитів

| Колонка      | Тип          | Обмеження                 | Опис                                 |
| ------------ | ------------ | ------------------------- | ------------------------------------ |
| `id`         | INT          | **AUTO_INCREMENT PK**     | Внутрішній ID                        |
| `action_key` | VARCHAR(255) | **NOT NULL**              | Ключ дії (напр. `login_192.168.1.1`) |
| `created_at` | TIMESTAMP    | DEFAULT CURRENT_TIMESTAMP | Час запиту                           |

**Індекси:**

- `idx_action_key` — на `action_key`
- `idx_created_at` — на `created_at`

> 📌 Клас `RateLimiter` (`includes/RateLimiter.php`) перевіряє кількість запитів за часовий інтервал.  
> 📌 Застосовується для: логіну, реєстрації, створення паст, API-запитів.

### Таблиця `jobs` — Черга фонових задач

| Колонка           | Тип                                                                                         | Обмеження                 | Опис                        |
| ----------------- | ------------------------------------------------------------------------------------------- | ------------------------- | --------------------------- |
| `id`              | VARCHAR(50)                                                                                 | **PRIMARY KEY**           | ID задачі                   |
| `type`            | ENUM('moderation_check','moderation_rewrite','email_verify','email_changed','email_custom') | **NOT NULL**              | Тип задачі                  |
| `status`          | ENUM('queued','processing','completed','failed','dead')                                     | DEFAULT 'queued'          | Статус задачі               |
| `payload`         | JSON                                                                                        | **NOT NULL**              | Дані задачі у форматі JSON  |
| `attempts`        | INT                                                                                         | DEFAULT 0                 | Кількість спроб виконання   |
| `max_attempts`    | INT                                                                                         | DEFAULT 3                 | Максимум спроб перед `dead` |
| `idempotency_key` | VARCHAR(255)                                                                                | NULL UNIQUE               | Захист від дублювання задач |
| `scheduled_at`    | DATETIME                                                                                    | DEFAULT CURRENT_TIMESTAMP | Запланований час виконання  |
| `started_at`      | DATETIME                                                                                    | NULL                      | Час початку обробки         |
| `completed_at`    | DATETIME                                                                                    | NULL                      | Час завершення              |
| `last_error`      | TEXT                                                                                        | NULL                      | Остання помилка             |
| `created_at`      | TIMESTAMP                                                                                   | DEFAULT CURRENT_TIMESTAMP | Час створення               |

**Індекси:**

- `idx_status_scheduled` — на `(status, scheduled_at)` — вибірка чергових задач
- `idx_type_status` — на `(type, status)` — фільтрація за типом
- `idx_idempotency` — на `idempotency_key`

**Типи задач:**

| Тип                  | Опис                                     |
| -------------------- | ---------------------------------------- |
| `moderation_check`   | Перевірка контенту на порушення через AI |
| `moderation_rewrite` | AI-переписування пасту з порушеннями     |
| `email_verify`       | Відправка листа верифікації email        |
| `email_changed`      | Сповіщення про зміну email               |
| `email_custom`       | Довільне email-повідомлення              |

**Статуси задач:**

| Статус       | Опис                                               |
| ------------ | -------------------------------------------------- |
| `queued`     | У черзі, очікує виконання                          |
| `processing` | Обробляється worker-ом                             |
| `completed`  | Успішно виконана                                   |
| `failed`     | Помилка (може бути повторна спроба)                |
| `dead`       | Вичерпано ліміт спроб (`attempts >= max_attempts`) |

> 📌 Worker: `cron/worker.php` або systemd-сервіс `paypaste-worker`.  
> 📌 Лінива обробка: `QUEUE_INLINE_PROBABILITY` — ймовірність обробки при веб-запиту.  
> 📌 `scheduled_at` підтримує exponential backoff при помилках.

---

## bad_words.json / bad_words.example.json — Фільтр модерації

Файли містять списки заборонених слів для простої модерації контенту. Використовуються `includes/Moderation.php`.

| Поле          | Логіка перевірки                                | Приклад                                                         |
| ------------- | ----------------------------------------------- | --------------------------------------------------------------- |
| `substrings`  | Містить підрядок (case-insensitive пошук)       | `"насильство"` → забороняє `"насильство"`, `"насильством"` тощо |
| `exact_words` | Точний збіг слова (з урахуванням границь слова) | `"сука"` → забороняє лише `"сука"`, але не `"насуйка"`          |

### bad_words.example.json

Шаблон з плейсхолдерами для нового розгортання:

```json
{
  "substrings": ["перелік_заборонених_підрядків_сюди"],
  "exact_words": ["перелік_цілих_заборонених_слів_сюди"]
}
```

---

## Взаємозв'язки з іншими папками

| Папка                             | Як використовує config/                                                             |
| --------------------------------- | ----------------------------------------------------------------------------------- |
| `includes/bootstrap.php`          | Підключає `config/db.php` (який підключає `config/config.php`) на кожному запиті    |
| `includes/models/`                | Усі моделі використовують `DB::getInstance()->getPDO()` з `db.php`                  |
| `includes/controllers/`           | Контролери читають константи з `config.php` (напр. `APP_URL`)                       |
| `includes/Moderation.php`         | Читає `bad_words.json` та використовує AI-константи                                 |
| `includes/mailer.php`             | Використовує `RESEND_API_KEY` та `MAIL_FROM`                                        |
| `includes/csrf.php`               | Використовує `COOKIE_SECRET` для HMAC-підпису                                       |
| `includes/webauthn.php`           | Використовує `WEBAUTHN_RP_ID`, `WEBAUTHN_ORIGIN`                                    |
| `api/oauth.php`                   | Використовує `TELEGRAM_LOGIN_BOT_TOKEN`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` |
| `api/webhooks/donatello.php`      | Використовує `DONATELLO_TOKEN` для перевірки підпису                                |
| `api/webhooks/telegram_stars.php` | Використовує `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`                        |
| `api/webhooks/verify_ad.php`      | Працює з таблицею `ad_events` з `paypaste.sql`                                      |
| `cron/ai_worker.php`              | Використовує AI-константи, таблицю `jobs`                                           |
| `cron/cleanup.php`                | Працює з таблицями `rate_limits`, `jobs`                                            |
| `cron/worker.php`                 | Читає `QUEUE_INLINE_PROBABILITY`, працює з таблицею `jobs`                          |
| `templates/`                      | Читають `ADSTERRA_*` константи для вставки рекламних скриптів                       |
| `admin/`                          | Підключає `bootstrap.php` → `db.php` → `config.php`                                 |

### Ланцюг підключення

```
Вхідний скрипт (index.php, view.php, ...)
  └── includes/bootstrap.php
        └── config/db.php
              └── config/config.php   ← визначає усі константи
              └── клас DB (Singleton PDO) ← підключення до MySQL
```

### Залежність констант від функцій

| Константа                                  | Де використовується                                | Функціональність               |
| ------------------------------------------ | -------------------------------------------------- | ------------------------------ |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | `config/db.php`                                    | Підключення до MySQL           |
| `APP_URL`                                  | `includes/controllers/`, шаблони                   | Формування redirect-URL        |
| `TELEGRAM_BOT_TOKEN`                       | `api/oauth.php`, `api/webhooks/telegram_stars.php` | Авторизація та платежі         |
| `TELEGRAM_LOGIN_BOT_TOKEN`                 | `api/oauth.php`                                    | Telegram Login Widget          |
| `TELEGRAM_WEBHOOK_SECRET`                  | `api/webhooks/telegram_stars.php`                  | Верифікація webhook-підпису    |
| `DONATELLO_TOKEN`                          | `api/webhooks/donatello.php`                       | Перевірка webhook-підпису      |
| `DONATELLO_URL`                            | `templates/credits.php`                            | Посилання на сторінку донату   |
| `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` | `api/oauth.php`                                    | GitHub OAuth flow              |
| `WEBAUTHN_RP_ID`, `WEBAUTHN_ORIGIN`        | `includes/webauthn.php`, `api/passkey.php`         | Реєстрація/авторизація passkey |
| `COOKIE_SECRET`                            | `includes/csrf.php`                                | HMAC-підпис Remember Me cookie |
| `ADSTERRA_*` (7 констант)                  | `templates/header.php`, `templates/paste_view.php` | Рекламні скрипти               |
| `OPENAI_API_KEY`                           | `cron/ai_worker.php`, `includes/Moderation.php`    | AI-модерація (резервний)       |
| `OLLAMA_*` (3 константи)                   | `cron/ai_worker.php`, `includes/Moderation.php`    | AI-модерація (основний)        |
| `RESEND_API_KEY`, `MAIL_FROM`              | `includes/mailer.php`                              | Відправка email                |
| `QUEUE_INLINE_PROBABILITY`                 | `includes/bootstrap.php`, `cron/worker.php`        | Лінива обробка черги           |

---

> 📌 **Важливо:** При зміні схеми БД — оновлюйте `paypaste.sql` та відповідні моделі в `includes/models/`.

---

## Перехресні посилання

| Пов'язаний документ                      | Що деталізує                                         |
| ---------------------------------------- | ---------------------------------------------------- |
| [includes.md](includes.md)               | Моделі та сервіси що використовують константи та БД  |
| [api.md](api.md)                         | API-endpoint'и що читають ключі та токени            |
| [templates.md](templates.md)             | Шаблони що використовують ADSTERRA/APP_URL константи |
| [admin-cron-data.md](admin-cron-data.md) | Worker та cleanup що використовують конфігурацію     |
| [architecture.md](architecture.md)       | Загальний огляд архітектури та екосистеми            |
