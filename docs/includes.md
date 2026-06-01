# Документація: `includes/`

> Модульна бібліотека серверного ядра PayPaste. Містить ініціалізацію, утиліти, контролери, моделі та сервіси.
>
> **Для новачка:** ця папка — «мотор» проекту. Усі файли тут підключаються через `bootstrap.php` і працюють разом як єдине ядро. Нижче кожен файл описано з прикладами використання.

---

## Зміст

1. [Огляд структури](#огляд-структури)
2. [Кореневі файли (includes/)](#кореневі-файли)
   - [bootstrap.php](#bootstrapphp)
   - [csrf.php](#csrfphp)
   - [jwt.php](#jwtphp)
   - [mailer.php](#mailerphp)
   - [Moderation.php](#moderationphp)
   - [RateLimiter.php](#ratelimiterphp)
   - [webauthn.php](#webauthnphp)
   - [Queue.php](#queuephp)
3. [Контролери (includes/controllers/)](#контролери)
   - [AuthController](#authcontrollerphp)
   - [PasteController](#pastecontrollerphp)
   - [SettingsController](#settingscontrollerphp)
   - [VerifyController](#verifycontrollerphp)
4. [Моделі (includes/models/)](#моделі)
   - [User](#userphp)
   - [Paste](#pastephp)
   - [Order](#orderphp)
   - [Transaction](#transactionphp)
   - [Passkey](#passkeyphp)
5. [Сервіси (includes/services/)](#сервіси)
   - [AuthService](#authservicephp)
   - [CreditService](#creditservicephp)
   - [PasteService](#pasteservicephp)
   - [AdQuestService](#adquestservicephp)
6. [Діаграма залежностей між модулями](#діаграма-залежностей-між-модулями)

---

## Огляд структури

```
includes/
├── bootstrap.php          # Центральна ініціалізація додатку
├── csrf.php               # CSRF-захист + Remember Me
├── jwt.php                # JWT-кодування/декодування (HS256)
├── mailer.php             # Відправка email через Resend API
├── Moderation.php         # Контент-модерація (OpenAI + локальна)
├── RateLimiter.php        # Захист від брутфорсу та спаму
├── webauthn.php           # WebAuthn/FIDO2 утиліти (реєстрація + вхід)
├── Queue.php              # Система черг задач (MySQL backend)
├── controllers/
│   ├── AuthController.php     # Реєстрація, вхід, вихід
│   ├── PasteController.php    # Створення, розблокування, AI-переписування паст
│   ├── SettingsController.php # Налаштування профілю, теми, видалення
│   └── VerifyController.php   # Верифікація email (OTP)
├── models/
│   ├── User.php           # Користувачі (профіль, баланс, OAuth)
│   ├── Paste.php          # Пасти (контент, теги, TTL, модерація)
│   ├── Order.php          # Замовлення поповнення балансу
│   ├── Transaction.php    # Фінансові транзакції
│   └── Passkey.php        # WebAuthn облікові дані
└── services/
    ├── AuthService.php    # Бізнес-логіка авторизації
    ├── CreditService.php  # Бізнес-логіка кредитів (атомарні операції)
    ├── PasteService.php   # Бізнес-логіка паст (створення, розблокування)
    └── AdQuestService.php # Рекламний квест (перегляд 3 реклам)
```

---

## Кореневі файли

### bootstrap.php

**Призначення:** Централізована ініціалізація додатку. Підключає БД, моделі, сесії, CSRF, чергу. Викликається з кожного entry point (`index.php`, `create.php`, `view.php` тощо).

**Ключові механізми:**

| Механізм          | Опис                                                                                            |
| ----------------- | ----------------------------------------------------------------------------------------------- |
| Ініціалізація     | Підключає `db.php`, `User.php`, `Paste.php`, `Queue.php`; стартує сесію (якщо не CLI)           |
| Кеш користувача   | `getCurrentUser()` кешує об'єкт `User` у `$_SESSION['_user_cache']`, оновлює баланс 1 раз/запит |
| Верифікація пошти | Глобальна перевірка: якщо `email_verified=0`, редирект на `verify.php`                          |
| Лінива черга      | З ймовірністю `QUEUE_INLINE_PROBABILITY` обробляє 1 задачу з черги inline                       |

**Функції:**

| Функція          | Сигнатура                          | Повернення        | Опис                                             |
| ---------------- | ---------------------------------- | ----------------- | ------------------------------------------------ |
| `getCurrentUser` | `getCurrentUser(): User\|null`     | `User` або `null` | Повертає авторизованого користувача з кешу сесії |
| `redirect`       | `redirect(string $location): void` | void              | HTTP-перенаправлення з `exit`                    |

**Inline-обробники черги** (дублюють логіку `cron/worker.php`):

| Функція                   | Тип задачі                | Опис                                  |
| ------------------------- | ------------------------- | ------------------------------------- |
| `inlineModerationCheck`   | `TYPE_MODERATION_CHECK`   | Перевірка через OpenAI Moderation API |
| `inlineModerationRewrite` | `TYPE_MODERATION_REWRITE` | Перефразування через Ollama           |
| `inlineEmailVerify`       | `TYPE_EMAIL_VERIFY`       | Відправка коду верифікації            |
| `inlineEmailChanged`      | `TYPE_EMAIL_CHANGED`      | Повідомлення про зміну email          |
| `inlineEmailCustom`       | `TYPE_EMAIL_CUSTOM`       | Довільне email-повідомлення           |

**Залежності:** `config/db.php`, `models/User.php`, `models/Paste.php`, `Queue.php`, `csrf.php`, `Moderation.php`, `mailer.php`

---

### csrf.php

**Призначення:** Захист від CSRF-атак + автоматичний логін через Remember Me cookie.

**Потік роботи:**

1. Підключає `AuthService` → перевіряє `$_COOKIE['remember_me']`
2. Генерує CSRF-токен, якщо відсутній у сесії
3. Надає функції `verify_csrf()` та `csrf_field()`

**Функції:**

| Функція       | Сигнатура              | Повернення     | Опис                                                                                                                  |
| ------------- | ---------------------- | -------------- | --------------------------------------------------------------------------------------------------------------------- |
| `verify_csrf` | `verify_csrf(): void`  | void           | Перевіряє `$_POST['csrf_token']` при POST; при невідповідності — редирект з помилкою; регенерує токен після перевірки |
| `csrf_field`  | `csrf_field(): string` | HTML `<input>` | Генерує приховане поле з CSRF-токеном                                                                                 |

**Залежності:** `services/AuthService.php`

---

### jwt.php

**Призначення:** Проста реалізація JWT (HS256) без зовнішніх залежностей. Використовує `COOKIE_SECRET` з конфігу.

**Клас `JWT`:**

| Метод    | Сигнатура                                 | Повернення         | Опис                                        |
| -------- | ----------------------------------------- | ------------------ | ------------------------------------------- |
| `encode` | `JWT::encode(array $payload): string`     | JWT-рядок          | Створює токен (header.payload.signature)    |
| `decode` | `JWT::decode(string $token): array\|null` | Payload або `null` | Декодує та верифікує токен; перевіряє `exp` |

**Приватні методи:** `base64UrlEncode()`, `base64UrlDecode()`

**Залежності:** `config/config.php` (константа `COOKIE_SECRET`)

---

### mailer.php

**Призначення:** Відправка email через [Resend API](https://resend.com). Підтримує 3 режими: черга (рекомендований), синхронний (fallback), прямий (для worker-а).

**Клас `Mailer`:**

| Метод                             | Сигнатура                                                                            | Повернення                                               | Опис                                                               |
| --------------------------------- | ------------------------------------------------------------------------------------ | -------------------------------------------------------- | ------------------------------------------------------------------ |
| `sendDirect`                      | `Mailer::sendDirect(string $to, string $subject, string $html): bool`                | `bool`                                                   | Пряма відправка через Resend API (без rate limiting, для worker)   |
| `enqueueVerificationEmail`        | `Mailer::enqueueVerificationEmail(string $to, string $code): array`                  | `['success'=>bool, 'job_id'=>?string, 'error'=>?string]` | Ставить верифікаційний лист у чергу; cooldown 180с, денний ліміт 3 |
| `enqueueEmailChangedNotification` | `Mailer::enqueueEmailChangedNotification(string $oldEmail, string $newEmail): array` | `['success'=>bool, 'job_id'=>?string]`                   | Ставить повідомлення про зміну email у чергу                       |
| `sendVerificationEmail`           | `Mailer::sendVerificationEmail(string $to, string $code): array`                     | `['success'=>bool, 'error'=>?string]`                    | Синхронний fallback; ті ж ліміти що й у черзі                      |
| `sendEmailChangedNotification`    | `Mailer::sendEmailChangedNotification(string $oldEmail, string $newEmail): bool`     | `bool`                                                   | Синхронний fallback для зміни email                                |

**Rate limiting (вбудований):**

| Ліміт    | Ключ                     | Порог | Вікно  |
| -------- | ------------------------ | ----- | ------ |
| Cooldown | `email_cooldown:{email}` | 1     | 180с   |
| Денний   | `email_daily:{email}`    | 3     | 86400с |

**Залежності:** `config/config.php`, `RateLimiter.php`, `Queue.php`

---

### Moderation.php

**Призначення:** Автоматична перевірка та перефразування контенту. Локальна перевірка (швидка, синхронна) + зовнішня OpenAI Moderation API (асинхронна через чергу) + AI-переписування через Ollama.

**Клас `Moderation`:**

| Метод           | Сигнатура                                               | Повернення                     | Опис                                                       |
| --------------- | ------------------------------------------------------- | ------------------------------ | ---------------------------------------------------------- |
| `check`         | `Moderation::check(string $text): array\|false`         | Категорії порушень або `false` | Повна перевірка: локальна → зовнішня                       |
| `localCheck`    | `Moderation::localCheck(string $text): array\|false`    | Категорії або `false`          | Локальна перевірка на стоп-слова з `config/bad_words.json` |
| `checkExternal` | `Moderation::checkExternal(string $text): array\|false` | Категорії або `false`          | OpenAI Moderation API; `false` = чистий або ключ відсутній |
| `rewrite`       | `Moderation::rewrite(string $text): string`             | Виправлений текст              | Перефразування через Ollama Cloud                          |

**Конфігурація:** `config/bad_words.json` (поля `substrings` та `exact_words`)

**Залежності:** `config/config.php` (константи `OPENAI_API_KEY`, `OLLAMA_API_KEY`, `OLLAMA_API_URL`, `OLLAMA_MODEL`)

---

### RateLimiter.php

**Призначення:** Захист від брутфорсу та спаму. Використовує MySQL-таблицю `rate_limits`. Підтримує багатофакторні ключі (користувач + браузер + IP-підмережа).

**Клас `RateLimiter`:**

| Метод                | Сигнатура                                                                                          | Повернення                       | Опис                                                                  |
| -------------------- | -------------------------------------------------------------------------------------------------- | -------------------------------- | --------------------------------------------------------------------- |
| `normalizeIpFactor`  | `RateLimiter::normalizeIpFactor(?string $ip=null): string`                                         | `/24` для IPv4, `::/64` для IPv6 | Нормалізує IP до підмережі                                            |
| `sessionFingerprint` | `RateLimiter::sessionFingerprint(): string`                                                        | SHA-256 хеш                      | Стабільний відбиток сесії + браузера                                  |
| `buildActionKey`     | `RateLimiter::buildActionKey(string $action, array $context=[]): string`                           | Ключ рядком                      | Будує складений ключ: `risk:{action}:{identity}:{device}:{ip_factor}` |
| `checkAction`        | `RateLimiter::checkAction(string $action, int $limit=10, int $window=60, array $context=[]): bool` | `bool`                           | Перевірка за основним ключем + м'який IP-velocity фактор              |
| `check`              | `RateLimiter::check(string $key, int $limit=10, int $window=60): bool`                             | `bool`                           | Базова перевірка: підрахунок записів у вікні, вставка нової спроби    |

**Алгоритм `check()`:**

1. Видалення старих записів за ключем (`DELETE ... WHERE created_at < NOW() - INTERVAL ? SECOND`)
2. Підрахунок поточних спроб (`SELECT COUNT(*)`)
3. Якщо < ліміт → `INSERT` нової спроби, `true`
4. Якщо ≥ ліміт → `false` (блокування)

**Алгоритм `checkAction()`:**

- Окрім основного ключа, перевіряє IP-velocity з окремим (більшим) лімітом
- IP-ліміт: `max(limit * 20, limit + 50)`

**Залежності:** `config/db.php`

---

### webauthn.php

**Призначення:** Набір утиліт для протоколу WebAuthn/FIDO2. Реєстрація Passkey (атестація) та вхід (асерція). Чистий PHP + OpenSSL, без Composer.

**Функції:**

| Функція                       | Сигнатура                                                                                              | Повернення                     | Опис                                                          |
| ----------------------------- | ------------------------------------------------------------------------------------------------------ | ------------------------------ | ------------------------------------------------------------- |
| `base64url_encode`            | `base64url_encode(string $data): string`                                                               | Base64URL рядок                | Кодування у Base64URL                                         |
| `base64url_decode`            | `base64url_decode(string $data): string`                                                               | Декодовані дані                | Декодування з Base64URL                                       |
| `generateChallenge`           | `generateChallenge(int $length=32): string`                                                            | Base64URL рядок                | Генерація випадкового челенджу                                |
| `getWebAuthnConfig`           | `getWebAuthnConfig(): array`                                                                           | `['rp_id','rp_name','origin']` | Конфігурація з `APP_URL`, `WEBAUTHN_RP_ID`, `WEBAUTHN_ORIGIN` |
| `verify_csrf_api`             | `verify_csrf_api(string $token): bool`                                                                 | `bool`                         | Перевірка CSRF для API-запитів                                |
| `cbor_decode`                 | `cbor_decode(string $data, int &$offset=0): mixed`                                                     | Декодовані дані                | Самописний CBOR-декодер                                       |
| `cose_to_pem`                 | `cose_to_pem(array $cose_key): string\|null`                                                           | PEM або `null`                 | Конвертація COSE (EC2 P-256) → PEM                            |
| `verify_attestation_response` | `verify_attestation_response(array $response, string $challenge, int $user_id): array`                 | Результат верифікації          | Перевірка реєстрації Passkey                                  |
| `verify_assertion_response`   | `verify_assertion_response(array $response, string $challenge, string $rp_id, object $passkey): array` | Результат верифікації          | Перевірка входу через Passkey                                 |
| `decode_cose_key`             | `decode_cose_key(string $pubkey_bytes): array`                                                         | COSE-масив                     | Допоміжний декодер COSE ключа                                 |

**Повернення `verify_attestation_response`:**

| Поле             | Тип            | Опис                         |
| ---------------- | -------------- | ---------------------------- |
| `success`        | `bool`         | Успіх верифікації            |
| `credential_id`  | `string\|null` | ID облікового запису         |
| `public_key_pem` | `string\|null` | Публічний ключ у PEM         |
| `counter`        | `int`          | Лічильник підписів           |
| `aaguid`         | `string`       | AAGUID автентифікатора       |
| `transports`     | `array`        | Способи передачі (USB, NFC…) |
| `error`          | `string`       | Тільки при `success=false`   |

**Повернення `verify_assertion_response`:**

| Поле          | Тип      | Опис                       |
| ------------- | -------- | -------------------------- |
| `success`     | `bool`   | Успіх перевірки підпису    |
| `new_counter` | `int`    | Новий лічильник            |
| `error`       | `string` | Тільки при `success=false` |

**Залежності:** `config/config.php` (константи `APP_URL`, `WEBAUTHN_RP_ID`, `WEBAUTHN_ORIGIN`, `COOKIE_SECRET`)

---

### Queue.php

**Призначення:** Єдина система черг задач для асинхронних інтеграцій. MySQL як backend. Підтримує ідемпотентність, експоненційний backoff, fallback при dead-задачах.

**Константи типів задач:**

| Константа                 | Значення             | Опис                            |
| ------------------------- | -------------------- | ------------------------------- |
| `TYPE_MODERATION_CHECK`   | `moderation_check`   | Перевірка контенту через OpenAI |
| `TYPE_MODERATION_REWRITE` | `moderation_rewrite` | Перефразування через Ollama     |
| `TYPE_EMAIL_VERIFY`       | `email_verify`       | Відправка коду верифікації      |
| `TYPE_EMAIL_CHANGED`      | `email_changed`      | Повідомлення про зміну email    |
| `TYPE_EMAIL_CUSTOM`       | `email_custom`       | Довільне email-повідомлення     |

**Константи станів:** `queued` → `processing` → `completed` | `failed` | `dead`

**Константи конфігурації:**

| Константа                 | Значення | Опис                                       |
| ------------------------- | -------- | ------------------------------------------ |
| `DEFAULT_MAX_ATTEMPTS`    | 3        | Максимальна кількість спроб                |
| `BACKOFF_BASE_SECONDS`    | 30       | Базова затримка ретраю                     |
| `CLAIM_TIMEOUT_SECONDS`   | 300      | Час після якого задача вважається завислою |
| `CLEANUP_OLDER_THAN_DAYS` | 7        | Дні для очищення старих задач              |

**Методи:**

| Метод                       | Сигнатура                                                                                                                   | Повернення                      | Опис                                                    |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------------- | ------------------------------- | ------------------------------------------------------- |
| `push`                      | `Queue::push(string $type, array $payload, ?string $idempotencyKey=null, int $maxAttempts=3, int $delaySeconds=0): ?string` | ID задачі або `null` (дублікат) | Постановка задачі з ідемпотентністю                     |
| `pop`                       | `Queue::pop(int $limit=5, ?string $type=null): array`                                                                       | Масив задач                     | Отримання задач для обробки (SELECT FOR UPDATE + claim) |
| `complete`                  | `Queue::complete(string $jobId): void`                                                                                      | void                            | Позначити задачу як завершену                           |
| `fail`                      | `Queue::fail(string $jobId, string $error): void`                                                                           | void                            | Позначити як невдалу (retry з backoff або dead)         |
| `getStatus`                 | `Queue::getStatus(string $jobId): ?array`                                                                                   | Статус або `null`               | Отримати статус задачі за ID                            |
| `getStatusByIdempotencyKey` | `Queue::getStatusByIdempotencyKey(string $key): ?array`                                                                     | Статус або `null`               | Статус за ідемпотентним ключем                          |
| `getMetrics`                | `Queue::getMetrics(): array`                                                                                                | Метрики черги                   | Кількість за статусами, середній час, помилки           |
| `cleanup`                   | `Queue::cleanup(int $olderThanDays=7): int`                                                                                 | Кількість видалених             | Очищення старих завершених/мертвих задач                |
| `getTypes`                  | `Queue::getTypes(): array`                                                                                                  | Масив типів                     | Список усіх типів задач                                 |

**Fallback при dead-задачах** (приватний `handleDeadFallback`):

- `moderation_check` → auto-approve пасту (локальна перевірка вже пройшла)
- `moderation_rewrite` → зняти прапорець `is_pending_rewrite`, опублікувати оригінал
- `email_*` → логування, адмін побачить

**Залежності:** `config/db.php`

---

## Контролери

Контролери обробляють HTTP-запити (POST), перевіряють CSRF, викликають сервіси та моделі, встановлюють `$_SESSION` повідомлення та роблять `header("Location: ...")` редиректи (PRG-патерн).

### AuthController.php

**Призначення:** Обробка реєстрації, входу та виходу користувачів.

**Методи:**

| Метод           | Сигнатура                                                                            | Дія POST   | Опис                                              |
| --------------- | ------------------------------------------------------------------------------------ | ---------- | ------------------------------------------------- |
| `handleRequest` | `handleRequest(): void`                                                              | —          | Маршрутизатор POST-запитів за `$_POST['action']`  |
| `register`      | `register(string $email, string $password, string $confirm, string $nickname): void` | `register` | Реєстрація з rate limiting; ставить email у чергу |
| `login`         | `login(string $email, string $password, bool $remember=false): void`                 | `login`    | Вхід з remember_me; rate limiting 5/60с           |
| `logout`        | `logout(): void`                                                                     | `logout`   | Видалення сесії + remember_me cookie              |

**Rate limiting:**

| Дія        | Ліміт | Вікно | IP-ліміт |
| ---------- | ----- | ----- | -------- |
| `register` | 5     | 60с   | 150      |
| `login`    | 5     | 60с   | 150      |

**Залежності:** `User.php`, `AuthService.php`, `csrf.php`, `RateLimiter.php`, `mailer.php`

---

### PasteController.php

**Призначення:** Обробка створення паст, розблокування платних паст та AI-переписування.

**Методи:**

| Метод               | Сигнатура                                                   | Дія POST              | Опис                                          |
| ------------------- | ----------------------------------------------------------- | --------------------- | --------------------------------------------- |
| `handleRequest`     | `handleRequest(): void`                                     | —                     | Маршрутизатор                                 |
| `create`            | `create(array $data, bool $is_pending_rewrite=false): void` | `create_paste`        | Локальна модерація → створення → черга OpenAI |
| `unlock`            | `unlock(array $data): void`                                 | `unlock_paste`        | Розблокування через CreditService             |
| `rewriteAndPublish` | `rewriteAndPublish(array $data): void`                      | `rewrite_and_publish` | Постановка AI-переписування у чергу           |

**Потік створення пасти:**

1. Rate limiting (5/60с, IP: 120)
2. Локальна модерація (`Moderation::localCheck`)
3. Створення через `PasteService::create` (статус `pending` для OpenAI)
4. Постановка `TYPE_MODERATION_CHECK` у чергу

**Rate limiting:**

| Дія            | Ліміт | Вікно | IP-ліміт |
| -------------- | ----- | ----- | -------- |
| `create_paste` | 5     | 60с   | 120      |
| `unlock_paste` | 10    | 60с   | 200      |

**Залежності:** `Paste.php`, `User.php`, `PasteService.php`, `CreditService.php`, `csrf.php`, `RateLimiter.php`, `Moderation.php`, `Queue.php`

---

### SettingsController.php

**Призначення:** Обробка налаштувань профілю, управління пастами, теми, OAuth, видалення акаунту.

**Методи:**

| Метод              | Сигнатура                                                                                 | Дія POST            | Опис                                                          |
| ------------------ | ----------------------------------------------------------------------------------------- | ------------------- | ------------------------------------------------------------- |
| `handleRequest`    | `handleRequest(): void`                                                                   | —                   | Маршрутизатор                                                 |
| `updateProfile`    | `updateProfile(string $nickname, string $password, string $confirm, string $email): void` | `update_profile`    | Оновлення нікнейму/пароля/email                               |
| `deletePaste`      | `deletePaste(string $paste_id): void`                                                     | `delete_paste`      | Видалення пасти власником                                     |
| `toggleVisibility` | `toggleVisibility(string $paste_id): void`                                                | `toggle_visibility` | Перемикання публічна↔приватна                                 |
| `unlinkAccount`    | `unlinkAccount(string $provider): void`                                                   | `unlink_account`    | Відв'язка GitHub/Telegram                                     |
| `updateTheme`      | `updateTheme(string $theme): void`                                                        | `update_theme`      | Зміна кольорової теми                                         |
| `deleteAccount`    | `deleteAccount(): void`                                                                   | `delete_account`    | Повне видалення акаунту (підтвердження: пароль/Passkey/OAuth) |
| `generateApiKey`   | `generateApiKey(): void`                                                                  | `generate_api_key`  | Генерація нового API-ключу                                    |

**Дозволені теми:** `retro`, `dark`, `terminal`, `light`, `github`, `retro-green`

**Залежності:** `User.php`, `Paste.php`, `AuthService.php`, `PasteService.php`, `csrf.php`, `mailer.php`

---

### VerifyController.php

**Призначення:** Верифікація email через OTP-код. Тільки для неверифікованих користувачів.

**Методи:**

| Метод           | Сигнатура                 | Дія POST      | Опис                                                          |
| --------------- | ------------------------- | ------------- | ------------------------------------------------------------- |
| `handleRequest` | `handleRequest(): void`   | —             | Перевіряє авторизацію та статус верифікації; маршрутизує POST |
| verify_code     | (всередині handleRequest) | `verify_code` | Перевірка 6-значного коду через `AuthService::verifyEmail`    |
| resend_code     | (всередині handleRequest) | `resend_code` | Генерація нового коду + постановка листа у чергу              |

**Залежності:** `bootstrap.php`, `mailer.php`, `AuthService.php`

---

## Моделі

Моделі — це OOP-класи для роботи з БД (CRUD). Використовують `DB::getInstance()->getPDO()` та prepared statements.

### User.php

**Призначення:** Модель користувача. Профіль, баланс кредитів, OAuth-ідентифікатори, розблоковані пасти, верифікація.

**Властивості:**

| Властивість                | Тип            | Опис                           |
| -------------------------- | -------------- | ------------------------------ |
| `$id`                      | `string`       | Унікальний ID (`u_...`)        |
| `$email`                   | `string`       | Електронна пошта               |
| `$telegram_id`             | `string\|null` | ID Telegram                    |
| `$github_id`               | `string\|null` | ID GitHub                      |
| `$password_hash`           | `string\|null` | Хеш пароля (null у кеші сесії) |
| `$nickname`                | `string`       | Нікнейм (макс. 50 символів)    |
| `$credits`                 | `int`          | Баланс кредитів                |
| `$unlocked_pastes`         | `array`        | ID розблокованих паст          |
| `$role`                    | `string`       | Роль (`user`, `admin`)         |
| `$theme`                   | `string`       | Кольорова тема                 |
| `$api_key`                 | `string\|null` | API-ключ (`pp_...`)            |
| `$email_verified`          | `int`          | 0/1                            |
| `$verification_code`       | `string\|null` | 6-значний OTP                  |
| `$verification_expires_at` | `string\|null` | Час закінчення коду            |

**Методи:**

| Метод              | Сигнатура                                                              | Повернення        | Опис                                                                                        |
| ------------------ | ---------------------------------------------------------------------- | ----------------- | ------------------------------------------------------------------------------------------- |
| `__construct`      | Конструктор з 14 параметрами                                           | `User`            | Створення екземпляра                                                                        |
| `save`             | `save(): void`                                                         | void              | INSERT ... ON DUPLICATE KEY UPDATE + синхронізація `unlocked_pastes` + оновлення кешу сесії |
| `hasUnlocked`      | `hasUnlocked(string $paste_id): bool`                                  | `bool`            | Чи має доступ до пасти                                                                      |
| `findByEmail`      | `User::findByEmail(string $email): ?User`                              | `User` або `null` | Пошук за email                                                                              |
| `findById`         | `User::findById(string $id): ?User`                                    | `User` або `null` | Пошук за ID                                                                                 |
| `findByTelegramId` | `User::findByTelegramId(string $id): ?User`                            | `User` або `null` | Пошук за Telegram ID                                                                        |
| `findByGithubId`   | `User::findByGithubId(string $id): ?User`                              | `User` або `null` | Пошук за GitHub ID                                                                          |
| `findByApiKey`     | `User::findByApiKey(string $key): ?User`                               | `User` або `null` | Пошук за API-ключем                                                                         |
| `countAll`         | `User::countAll(string $search=''): int`                               | `int`             | Кількість користувачів (з пошуком)                                                          |
| `getAll`           | `User::getAll(int $limit=25, int $offset=0, string $search=''): array` | Масив рядків БД   | Список з пагінацією                                                                         |

**Залежності:** `config/db.php`

---

### Paste.php

**Призначення:** Модель пасти. Контент, теги, TTL, модерація, мова програмування.

**Властивості:**

| Властивість           | Тип            | Опис                                     |
| --------------------- | -------------- | ---------------------------------------- |
| `$id`                 | `string`       | Унікальний ID (`p_` + `random_bytes(8)`) |
| `$title`              | `string`       | Назва                                    |
| `$content`            | `string`       | Текстовий контент                        |
| `$user_id`            | `string\|null` | ID автора (null = анонім)                |
| `$is_paid`            | `bool`         | Чи платна                                |
| `$is_private`         | `bool`         | Чи приватна                              |
| `$view_cost`          | `int`          | Вартість перегляду                       |
| `$created_at`         | `string`       | Дата створення                           |
| `$expires_at`         | `string\|null` | TTL (null = без обмежень)                |
| `$is_pending_rewrite` | `bool`         | Чи в черзі AI-переписування              |
| `$moderation_status`  | `string`       | `pending`, `approved`, `rejected`        |
| `$moderation_result`  | `string\|null` | JSON з категоріями порушень              |
| `$language`           | `string`       | Мова/синтаксис (за замовч. `plaintext`)  |

**Методи:**

| Метод                 | Сигнатура                                                                            | Повернення           | Опис                                           |
| --------------------- | ------------------------------------------------------------------------------------ | -------------------- | ---------------------------------------------- |
| `isExpired`           | `isExpired(): bool`                                                                  | `bool`               | Перевірка TTL                                  |
| `save`                | `save(): void`                                                                       | void                 | INSERT ... ON DUPLICATE KEY UPDATE             |
| `update`              | `update(): bool`                                                                     | `bool`               | UPDATE для адмін-панелі                        |
| `syncTags`            | `syncTags(string $tags_input=''): void`                                              | void                 | Парсинг тегів → DELETE старих → INSERT нових   |
| `getTags`             | `getTags(): array`                                                                   | Масив рядків         | Теги пасти                                     |
| `getTagsByPopularity` | `getTagsByPopularity(): array`                                                       | Масив рядків         | Теги, відсортовані за глобальною популярністю  |
| `getTagColor`         | `Paste::getTagColor(string $tag): string`                                            | `#RRGGBB`            | Стабільний колір з MD5 хешу тегу               |
| `stripTags`           | `Paste::stripTags(string $content): string`                                          | Очищений текст       | Видалення `#тег` з тексту                      |
| `findById`            | `Paste::findById(string $id): ?Paste`                                                | `Paste` або `null`   | Пошук з ледачим видаленням протермінованих     |
| `findAllPublic`       | `Paste::findAllPublic(int $limit=20, string $category='all', string $tag=''): array` | `Paste[]`            | Публічні непротерміновані, без pending_rewrite |
| `findByUserId`        | `Paste::findByUserId(string $user_id): array`                                        | `Paste[]`            | Пасти користувача                              |
| `countAll`            | `Paste::countAll(string $search='', string $tag=''): int`                            | `int`                | Кількість паст                                 |
| `getAllPastes`        | `Paste::getAllPastes(int $limit=25, int $offset=0, string $search=''): array`        | Масив рядків         | Для адмін-панелі                               |
| `getPopularTags`      | `Paste::getPopularTags(int $limit=10): array`                                        | Масив `[tag, count]` | Популярні теги                                 |

**Категорії `findAllPublic`:** `all`, `paid`, `free`, `user`, `anonymous`

**Залежності:** `config/db.php`

---

### Order.php

**Призначення:** Модель замовлення поповнення балансу. Відстеження платежів через зовнішні сервіси (Donatello, Telegram Stars).

**Властивості:**

| Властивість             | Тип            | Опис                                          |
| ----------------------- | -------------- | --------------------------------------------- |
| `$id`                   | `string\|null` | ID замовлення                                 |
| `$user_id`              | `string\|null` | ID користувача                                |
| `$service`              | `string\|null` | Сервіс оплати (`donatello`, `telegram_stars`) |
| `$amount_credits`       | `int\|null`    | Кількість кредитів                            |
| `$status`               | `string`       | `pending`, `completed`, `failed`              |
| `$external_provider_id` | `string\|null` | ID транзакції у зовнішній системі             |
| `$created_at`           | `string`       | Дата створення                                |
| `$updated_at`           | `string`       | Дата останнього оновлення                     |

**Методи:**

| Метод         | Сигнатура                             | Повернення         | Опис                               |
| ------------- | ------------------------------------- | ------------------ | ---------------------------------- |
| `__construct` | `__construct(array $data=[])`         | `Order`            | Створення з масиву даних           |
| `save`        | `save(): void`                        | void               | INSERT ... ON DUPLICATE KEY UPDATE |
| `findById`    | `Order::findById(string $id): ?Order` | `Order` або `null` | Пошук за ID                        |

**Залежності:** `config/db.php`

---

### Transaction.php

**Призначення:** Модель фінансових транзакцій. Аудит усіх операцій з кредитами.

**Властивості:**

| Властивість         | Тип            | Опис                                                                       |
| ------------------- | -------------- | -------------------------------------------------------------------------- |
| `$id`               | `int\|null`    | Auto-increment ID                                                          |
| `$user_id`          | `string\|null` | ID користувача                                                             |
| `$amount`           | `int`          | Сума (позитивна — нарахування, негативна — списання)                       |
| `$type`             | `string`       | Тип: `topup`, `creation_fee`, `purchase`, `sale`, `api_usage`, `ad_reward` |
| `$service`          | `string\|null` | Джерело                                                                    |
| `$related_paste_id` | `string\|null` | Пов'язана паста                                                            |
| `$related_order_id` | `string\|null` | Пов'язане замовлення                                                       |
| `$description`      | `string\|null` | Опис                                                                       |
| `$idempotency_key`  | `string\|null` | Ключ ідемпотентності                                                       |
| `$created_at`       | `string`       | Дата створення                                                             |

**Методи:**

| Метод       | Сигнатура                                                                                                                      | Повернення    | Опис                                 |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------ | ------------- | ------------------------------------ |
| `save`      | `save(): void`                                                                                                                 | void          | INSERT (новий) або UPDATE (існуючий) |
| `countAll`  | `Transaction::countAll(string $type=''): int`                                                                                  | `int`         | Загальна кількість                   |
| `count`     | `Transaction::count(string $type=''): int`                                                                                     | `int`         | Підрахунок (за типом або усіх)       |
| `getAll`    | `Transaction::getAll(int $limit=50, int $offset=0, string $type=''): array`                                                    | Масив рядків  | З JOIN по користувачах               |
| `sumTopups` | `Transaction::sumTopups(): int`                                                                                                | `int`         | Сума усіх поповнень                  |
| `create`    | `Transaction::create($user_id, $amount, $type, $service=null, $paste_id=null, $order_id=null, $description=null): Transaction` | `Transaction` | Статичний хелпер                     |

**Залежності:** `config/db.php`

---

### Passkey.php

**Призначення:** Модель WebAuthn облікових даних (Passkey). Зберігання публічних ключів, лічильників, AAGUID.

**Властивості:**

| Властивість       | Тип            | Опис                         |
| ----------------- | -------------- | ---------------------------- |
| `$id`             | `string`       | Внутрішній ID (`pk_...`)     |
| `$user_id`        | `string`       | ID власника                  |
| `$credential_id`  | `string`       | WebAuthn Credential ID       |
| `$public_key_pem` | `string`       | Публічний ключ у PEM         |
| `$counter`        | `int`          | Лічильник підписів           |
| `$aaguid`         | `string\|null` | AAGUID автентифікатора       |
| `$transports`     | `string`       | JSON-масив способів передачі |
| `$created_at`     | `string`       | Дата створення               |

**Методи:**

| Метод                | Сигнатура                                                | Повернення           | Опис                               |
| -------------------- | -------------------------------------------------------- | -------------------- | ---------------------------------- |
| `getTransportsArray` | `getTransportsArray(): array`                            | Масив                | Декодує JSON transports            |
| `save`               | `save(): void`                                           | void                 | INSERT ... ON DUPLICATE KEY UPDATE |
| `findByCredentialId` | `Passkey::findByCredentialId(string $id): ?Passkey`      | `Passkey` або `null` | Пошук за Credential ID             |
| `findByUserId`       | `Passkey::findByUserId(string $id): array`               | `Passkey[]`          | Усі ключі користувача              |
| `countByUserId`      | `Passkey::countByUserId(string $id): int`                | `int`                | Кількість ключів (макс. 5)         |
| `updateCounter`      | `updateCounter(int $new_counter): void`                  | void                 | Оновлення лічильника               |
| `deleteById`         | `Passkey::deleteById(string $id, string $user_id): void` | void                 | Видалення конкретного ключа        |
| `deleteByUserId`     | `Passkey::deleteByUserId(string $user_id): void`         | void                 | Видалення усіх ключів користувача  |

**Залежності:** `config/db.php`

---

## Сервіси

Сервіси інкапсулюють бізнес-логіку. Контролери делегують їм складні операції. Сервіси можуть взаємодіяти з кількома моделями та між собою.

> **Важливо:** зв'язки між сервісами **односторонні**. `PasteService` викликає `CreditService` (для розрахунку комісій та покупок), але `CreditService` нічого не знає про `PasteService`. `AdQuestService` не викликається з інших сервісів — використовується напряму з `view.php` та `api/webhooks/verify_ad.php`.

### AuthService.php

**Призначення:** Бізнес-логіка авторизації. Реєстрація, вхід, OAuth, Passkey, Remember Me, верифікація email, управління профілем.

**Методи:**

| Метод                      | Сигнатура                                                                                                                 | Повернення                | Опис                                           |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------- | ------------------------- | ---------------------------------------------- |
| `register`                 | `AuthService::register(string $email, string $password, string $confirm, string $nickname): User`                         | `User`                    | Реєстрація з валідацією; +100 кредитів         |
| `login`                    | `AuthService::login(string $email, string $password, bool $remember=false): User`                                         | `User`                    | Вхід з optional remember_me                    |
| `logout`                   | `AuthService::logout(): void`                                                                                             | void                      | Знищення сесії + cookie                        |
| `setSession`               | `AuthService::setSession(User $user): void`                                                                               | void                      | Встановлення `$_SESSION['user_id']`            |
| `setRememberCookie`        | `AuthService::setRememberCookie(User $user): void`                                                                        | void                      | HMAC-токен: `user_id:hmac(id+hash)`, 14 днів   |
| `clearRememberCookie`      | `AuthService::clearRememberCookie(): void`                                                                                | void                      | Видалення cookie                               |
| `checkRememberMe`          | `AuthService::checkRememberMe(): ?User`                                                                                   | `User` або `null`         | Перевірка cookie + авторестор сесії            |
| `oauthLogin`               | `AuthService::oauthLogin(string $provider, array $oauthData): User`                                                       | `User`                    | OAuth вхід/реєстрація (GitHub/Telegram)        |
| `linkOAuth`                | `AuthService::linkOAuth(User $user, string $provider, string $providerId): bool`                                          | `bool`                    | Прив'язка OAuth до існуючого акаунту           |
| `unlinkOAuth`              | `AuthService::unlinkOAuth(User $user, string $provider): bool`                                                            | `bool`                    | Відв'язка OAuth                                |
| `passkeyLogin`             | `AuthService::passkeyLogin(string $credentialId): User`                                                                   | `User`                    | Вхід через Passkey                             |
| `registerPasskey`          | `AuthService::registerPasskey(string $userId, array $credentialData): Passkey`                                            | `Passkey`                 | Реєстрація нового Passkey (макс. 5)            |
| `generateApiKey`           | `AuthService::generateApiKey(User $user): string`                                                                         | `string`                  | Генерація `pp_...` ключа                       |
| `updateProfile`            | `AuthService::updateProfile(User $user, string $nickname, ?string $password, ?string $confirm, ?string $newEmail): array` | `['email_changed'=>bool]` | Оновлення профілю з валідацією                 |
| `deleteAccount`            | `AuthService::deleteAccount(User $user): bool`                                                                            | `bool`                    | Повне видалення акаунту + Passkeys             |
| `isAdmin`                  | `AuthService::isAdmin(?User $user): bool`                                                                                 | `bool`                    | Перевірка ролі `admin`                         |
| `isOwnerOrAdmin`           | `AuthService::isOwnerOrAdmin(?User $user, ?string $ownerId): bool`                                                        | `bool`                    | Перевірка прав власника/адміна                 |
| `verifyEmail`              | `AuthService::verifyEmail(User $user, string $code): bool`                                                                | `bool`                    | Верифікація OTP-коду (15 хв TTL)               |
| `generateVerificationCode` | `AuthService::generateVerificationCode(User $user): string`                                                               | 6-значний код             | Генерація нового OTP                           |
| `registerPasskeyUser`      | `AuthService::registerPasskeyUser(string $email, string $password, string $nickname): User`                               | `User`                    | Реєстрація користувача для Passkey-авторизації |
| `deleteByAdmin`            | `AuthService::deleteByAdmin(string $userId): bool`                                                                        | `bool`                    | Видалення користувача адміном                  |

**Remember Me механізм:**

- Токен: `{user_id}:{hmac_sha256(user_id + password_hash, COOKIE_SECRET)}`
- Cookie: `remember_me` (httponly, secure, samesite=Lax, 14 днів)
- Cookie: `remember_email` (не httponly, для автозаповнення)

**Залежності:** `config/db.php`, `User.php`, `Passkey.php`

---

### CreditService.php

**Призначення:** Атомарні фінансові операції з кредитами. Забезпечує цілісність балансу через PDO-транзакції, `FOR UPDATE`, умовні `UPDATE`, ідемпотентність.

**Інваріанти:**

- `deduct`/`credit`/`transfer` — атомарні через PDO-транзакції з `FOR UPDATE`
- Умовне списання: `UPDATE ... WHERE credits >= ?` (захист від race conditions)
- Ідемпотентність: повторний виклик з тим самим `idempotencyKey` не змінює баланс
- Аудит: `error_log` для кожної фінансової події

**Методи:**

| Метод                   | Сигнатура                                                                                                                                                                                          | Повернення                             | Опис                                                                                        |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- | ------------------------------------------------------------------------------------------- |
| `calculateCreationCost` | `CreditService::calculateCreationCost(string $content): int`                                                                                                                                       | `int`                                  | `ceil(mb_strlen($content) / 10)`                                                            |
| `hasEnoughCredits`      | `CreditService::hasEnoughCredits(User $user, int $amount): bool`                                                                                                                                   | `bool`                                 | Перевірка балансу                                                                           |
| `deduct`                | `CreditService::deduct(User $user, int $amount, string $type, ?string $description=null, ?string $pasteId=null, ?string $orderId=null, ?string $service=null, ?string $idempotencyKey=null): bool` | `bool`                                 | Атомарне списання                                                                           |
| `credit`                | `CreditService::credit(User $user, int $amount, string $type, ?string $description=null, ?string $pasteId=null, ?string $orderId=null, ?string $service=null, ?string $idempotencyKey=null): bool` | `bool`                                 | Атомарне нарахування                                                                        |
| `transfer`              | `CreditService::transfer(User $from, User $to, int $amount, string $type, ?string $description=null, ?string $pasteId=null, ?string $service=null, ?string $idempotencyKey=null): bool`            | `bool`                                 | Атомарний переказ (блокування обох балансів у фіксованому порядку для запобігання deadlock) |
| `purchasePasteAccess`   | `CreditService::purchasePasteAccess(User $buyer, ?User $author, string $pasteId, int $amount): array`                                                                                              | `['success'=>bool, 'message'=>string]` | Атомарна купівля доступу; INSERT IGNORE у `unlocked_pastes`                                 |

**Типи транзакцій:** `topup`, `creation_fee`, `purchase`, `sale`, `api_usage`, `ad_reward`

**Залежності:** `config/db.php`, `Transaction.php`, `User.php`

---

### PasteService.php

**Призначення:** Бізнес-логіка паст. Створення, розблокування, доступ, файли, видалення, TTL-очищення.

**Методи:**

| Метод              | Сигнатура                                                                                                                           | Повернення                             | Опис                                                        |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- | ----------------------------------------------------------- |
| `create`           | `PasteService::create(array $data, ?string $userId=null, bool $isPendingRewrite=false, string $moderationStatus='approved'): Paste` | `Paste`                                | Створення з валідацією, списання `creation_fee`, теги, файл |
| `unlock`           | `PasteService::unlock(string $pasteId, string $userId): array`                                                                      | `['success'=>bool, 'message'=>string]` | Розблокування через `CreditService::purchasePasteAccess`    |
| `canAccess`        | `PasteService::canAccess(Paste $paste, ?User $user): bool`                                                                          | `bool`                                 | Перевірка доступу (автор/адмін/куплено/безкоштовна)         |
| `isLocked`         | `PasteService::isLocked(Paste $paste, ?User $user): bool`                                                                           | `bool`                                 | Чи потрібна оплата                                          |
| `handleFileUpload` | `PasteService::handleFileUpload(string $pasteId, array $file): string`                                                              | Шлях файлу                             | Завантаження файлу (макс. 5 МБ, перевірка MIME+розширення)  |
| `delete`           | `PasteService::delete(string $pasteId, ?string $userId=null): bool`                                                                 | `bool`                                 | Видалення пасти + файлів з перевіркою прав                  |
| `toggleVisibility` | `PasteService::toggleVisibility(string $pasteId, string $userId): bool`                                                             | `bool`                                 | Перемикання приватності                                     |
| `cleanupExpired`   | `PasteService::cleanupExpired(): int`                                                                                               | `int`                                  | Масове видалення протермінованих                            |

**Дозволені типи файлів:**

| Розширення    | MIME                                                                   |
| ------------- | ---------------------------------------------------------------------- |
| `jpg`, `jpeg` | `image/jpeg`                                                           |
| `png`         | `image/png`                                                            |
| `gif`         | `image/gif`                                                            |
| `zip`         | `application/zip`, `application/x-zip-compressed`, `application/x-zip` |
| `pdf`         | `application/pdf`                                                      |
| `txt`         | `text/plain`                                                           |

**Залежності:** `config/db.php`, `Paste.php`, `User.php`, `CreditService.php`

---

### AdQuestService.php

**Призначення:** Серверно-авторитативна модель рекламного квесту. 3 рекламні події = безкоштовний доступ до платної пасти. Захист від replay через унікальний `nonce` у БД (`ad_events`).

**Інваріанти:**

- Одна валідна рекламна подія = одне зарахування (`UNIQUE nonce` у `ad_events`)
- Повторний виклик з тим самим nonce → `INSERT IGNORE` → дублікат не створюється
- Підміна сесії не дозволяє повторне нарахування (БД — авторитет)
- HMAC-підписані токени з `nonce`, `step`, `quest_id`, TTL 900с

**Константи:**

| Константа         | Значення | Опис                         |
| ----------------- | -------- | ---------------------------- |
| `REQUIRED_EVENTS` | 3        | Кількість реклам для доступу |
| `TOKEN_TTL`       | 900      | Час життя токена (15 хвилин) |

**Методи:**

| Метод                        | Сигнатура                                                                                  | Повернення  | Опис                                                              |
| ---------------------------- | ------------------------------------------------------------------------------------------ | ----------- | ----------------------------------------------------------------- |
| `setMinSecondsBetweenEvents` | `AdQuestService::setMinSecondsBetweenEvents(int $seconds): void`                           | void        | Налаштування мінімального інтервалу                               |
| `getMinSecondsBetweenEvents` | `AdQuestService::getMinSecondsBetweenEvents(): int`                                        | `int`       | Поточний інтервал (за замовч. 10с)                                |
| `identityHash`               | `AdQuestService::identityHash(?string $userId=null): string`                               | SHA-256 хеш | Стабільний ідентифікатор: `user:{id}` або `session:{fingerprint}` |
| `getQuestId`                 | `AdQuestService::getQuestId(string $pasteId, ?string $userId=null): string`                | Quest ID    | Створення/отримання ID квесту (кеш у сесії)                       |
| `issueToken`                 | `AdQuestService::issueToken(string $pasteId, ?string $userId=null): string`                | HMAC-токен  | Створення підписаного токена з nonce для наступної події          |
| `progress`                   | `AdQuestService::progress(string $pasteId, ?string $userId=null): int`                     | `int`       | Кількість зарахованих подій з БД                                  |
| `hasAccess`                  | `AdQuestService::hasAccess(string $pasteId, ?string $userId=null): bool`                   | `bool`      | Чи завершено квест (progress ≥ 3)                                 |
| `verifyEvent`                | `AdQuestService::verifyEvent(string $pasteId, string $token, ?string $userId=null): array` | Результат   | Валідація та зарахування події (14 перевірок!)                    |

**Результат `verifyEvent`:**

| Поле          | Тип            | Опис                                       |
| ------------- | -------------- | ------------------------------------------ |
| `success`     | `bool`         | Успіх зарахування                          |
| `ads_watched` | `int`          | Зараховано подій                           |
| `remaining`   | `int`          | Залишилось до завершення                   |
| `done`        | `bool`         | Чи квест завершено                         |
| `next_token`  | `string\|null` | Токен для наступної події (null якщо done) |
| `reason`      | `string`       | Тільки при `success=false`                 |
| `message`     | `string`       | Тільки при `success=false`                 |

**Причини відхилення (`reason`):**

| Reason                 | Опис                                    |
| ---------------------- | --------------------------------------- |
| `rate_limited`         | Перевищено ліміт запитів                |
| `bad_signature`        | Некоректний підпис токена               |
| `paste_mismatch`       | Токен не належить цій пасті             |
| `user_mismatch`        | Токен не належить поточному користувачу |
| `identity_mismatch`    | Ідентичність не збігається              |
| `fingerprint_mismatch` | Відбиток браузера не збігається         |
| `expired`              | Токен застарів                          |
| `quest_mismatch`       | Токен належить іншому квесту            |
| `missing_nonce`        | Відсутній nonce                         |
| `replay`               | Повторне використання nonce             |
| `unknown_nonce`        | Nonce не був виданий сервером           |
| `issued_expired`       | Виданий nonce застарів                  |
| `too_fast`             | Завто швидке підтвердження              |
| `step_mismatch`        | Некоректний порядок кроку               |

**Залежності:** `RateLimiter.php`, `config/db.php` (через `DB::getInstance()`)

---

## Діаграма залежностей між модулями

![](./img/Component%20diagram.png)

**Хто кого викликає (основні шляхи):**

| Викликати            | Викликає                                                              |
| -------------------- | --------------------------------------------------------------------- |
| `AuthController`     | `AuthService`, `RateLimiter`, `Mailer`, `csrf`                        |
| `PasteController`    | `PasteService`, `CreditService`, `Moderation`, `Queue`, `RateLimiter` |
| `SettingsController` | `AuthService`, `PasteService`, `Mailer`                               |
| `VerifyController`   | `AuthService`, `Mailer`                                               |
| `PasteService`       | `CreditService`, `Paste`, `User`                                      |
| `CreditService`      | `Transaction`, `User` (через PDO)                                     |
| `AuthService`        | `User`, `Passkey`                                                     |
| `Mailer`             | `RateLimiter`, `Queue`                                                |
| `AdQuestService`     | `RateLimiter`, `DB` (напряму)                                         |
| `bootstrap.php`      | `Queue`, `Moderation`, `Mailer` (inline fallback)                     |
| `csrf.php`           | `AuthService` (Remember Me)                                           |
| `webauthn.php`       | `config` (константи), `Passkey` (через API endpoint)                  |

---

## БД-таблиці, що використовуються модулями `includes/`

| Таблиця           | Модулі, що працюють з нею                                                                          |
| ----------------- | -------------------------------------------------------------------------------------------------- |
| `users`           | `User.php`, `CreditService.php`, `AuthService.php`, `bootstrap.php`                                |
| `pastes`          | `Paste.php`, `PasteService.php`, `Moderation.php` (через bootstrap inline), `Queue.php` (fallback) |
| `paste_tags`      | `Paste.php` (`syncTags`, `getTags`, `getTagsByPopularity`, `getPopularTags`)                       |
| `unlocked_pastes` | `User.php`, `CreditService.php`                                                                    |
| `transactions`    | `Transaction.php`, `CreditService.php`                                                             |
| `orders`          | `Order.php`                                                                                        |
| `passkeys`        | `Passkey.php`, `AuthService.php`                                                                   |
| `jobs`            | `Queue.php`                                                                                        |
| `rate_limits`     | `RateLimiter.php`                                                                                  |
| `ad_events`       | `AdQuestService.php`                                                                               |

---

## Перехресні посилання

| Пов'язаний документ                      | Що деталізує                                           |
| ---------------------------------------- | ------------------------------------------------------ |
| [config.md](config.md)                   | Константи що використовуються модулями, повна схема БД |
| [api.md](api.md)                         | REST API та webhooks що викликають сервіси та моделі   |
| [templates.md](templates.md)             | Шаблони що використовують контролери та моделі         |
| [admin-cron-data.md](admin-cron-data.md) | Worker що обробляє чергу Queue, адмін-панель           |
| [architecture.md](architecture.md)       | Загальний огляд архітектури та екосистеми              |
