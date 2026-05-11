# PayPaste — Загальна архітектура проекту

> Документ верхнього рівня. Почніть читання звідси.
> Останнє оновлення: 2026-05-11

---

## 1. Огляд проекту

**PayPaste** — це веб-сервіс для збереження та поширення текстових і кодових фрагментів. Аналог популярних сервісів типу Pastebin або GitHub Gist, але з **монетизацією доступу**: автор може зробити свій фрагмент платним, і інші користувачі мають заплатити (або подивитись рекламу), щоб побачити вміст.

**UI стилізовано** під дизайн піратських сайтів початку 2000-х — яскраві кольори, анімації, банерна реклама. Це навмисний стилістичний вибір для навчального проекту.

### Цільові користувачі

| Роль              | Опис                                                                       |
| ----------------- | -------------------------------------------------------------------------- |
| **Автор (user)**  | Створює пасти, продає доступ, отримує кредити за покупки                   |
| **Покупець**      | Оплачує доступ до платних паст або проходить рекламний квест               |
| **Адміністратор** | Повний CRUD над користувачами, пастами, транзакціями; перегляд черги задач |

### Ключові лінки

- Репозиторій: корінь проекту
- БД: MySQL `paypaste` (див. [docs/config.md](config.md))
- Розгортання: PHP/Apache/Nginx + MySQL + опціональний CLI worker

---

## 2. Глосарій

| Термін                    | Пояснення                                                                                            |
| ------------------------- | ---------------------------------------------------------------------------------------------------- |
| **Паста / Paste**         | Збережений текстовий/кодовий фрагмент. Може бути публічним, приватним або платним                    |
| **Кредити**               | Внутрішня валюта: 1 кредит ≈ умовна одиниця. Новий акаунт = 100 кредитів                             |
| **Квест / Ad Quest**      | Спосіб безкоштовно отримати доступ до платної пасти: переглянути 3 рекламних ролики (по 10 сек)      |
| **Creation fee**          | Комісія за створення платної пасти: `ceil(довжина_вмісту / 10)` кредитів                             |
| **Donatello**             | Український сервіс для прийому донатів/оплат. Використовується для поповнення кредитами через картку |
| **Telegram Stars**        | Внутрішня валюта Telegram для оплати в ботах. XTR — код валюти                                       |
| **Adsterra**              | Рекламна мережа, що надає банери, popunder та Social Bar. Джерело реклами для квестів                |
| **WebAuthn / Passkey**    | Стандарт безпарольної автентифікації (відпечаток, Face ID). Заміна паролю                            |
| **Idempotency key**       | Унікальний ключ операції, що запобігає дублюванню при повторних запитах                              |
| **Bootstrap**             | Центральний файл ініціалізації (`includes/bootstrap.php`), що підключає БД, сесії, моделі, CSRF      |
| **Entry Script**          | PHP-файл у корені проекту (напр. `view.php`), що є точкою входу для HTTP-запиту                      |
| **Inline Queue Fallback** | Запуск обробки черги безпосередньо під час HTTP-запиту, якщо фоновий worker не запущений             |
| **Lazy Expiration**       | Видалення протермінованої пасти при спробі доступу, а не за розкладом                                |

---

## 3. Quick Start

### 3.1 Встановлення (5 хвилин)

```bash
# 1. Клонувати репозиторій
git clone <repo-url> && cd paypaste

# 2. Створити базу даних
mysql -u root -p -e "CREATE DATABASE paypaste CHARACTER SET utf8mb4"
mysql -u root -p paypaste < config/paypaste.sql

# 3. Налаштувати конфігурацію
cp config/config.example.php config/config.php
# → Відкрити config/config.php і заповнити: DB_HOST, DB_USER, DB_PASS, APP_URL
# → Для повного функціоналу: GitHub/Telegram OAuth ключі, Resend API ключ, Adsterra ID

# 4. Налаштувати веб-сервер
# Apache: DocumentRoot → корінь проекту (.htaccess вже налаштований)
# Nginx: скопіювати ops/nginx.example.conf у /etc/nginx/sites-available/
```

Відкрити `http://your-domain/` — має відобразитися головна сторінка.

### 3.2 Перший сценарій використання

1. **Відкрити головну** → `index.php` — список публічних паст
2. **Зареєструватись** → натиснути «Увійти» → вкладка «Реєстрація» → отримаєте 100 кредитів
3. **Підтвердити email** → на пошту прийде 6-значний OTP-код → ввести на `verify.php`
4. **Створити пасту** → «Створити» → ввести текст → вибрати «Платна» → вказати вартість → спишуться кредити (creation fee)
5. **Переглянути чужу платну пасту** → або купити за кредити, або пройти квест (3 реклами)
6. **Поповнити баланс** → `credits.php` → Donatello або Telegram Stars
7. **API-доступ** → згенерувати API-ключ у `settings.php` → отримати JWT через `api/auth_token.php`

---

## 4. Архітектура (спрощений MVC)

Проект використовує **спрощений MVC** — варіант класичного патерну Model-View-Controller, адаптований під чистий PHP без фреймворку.

### Чим відрізняється від класичного MVC

| Класичний MVC (Laravel, Django)               | PayPaste (спрощений)                              |
| --------------------------------------------- | ------------------------------------------------- |
| Є Router — один вхідний файл, URL → контролер | Немає Router — кожна сторінка = окремий .php-файл |
| Шаблонизатор (Blade, Jinja)                   | Чистий PHP в шаблонах (`<?php echo ... ?>`)       |
| Dependency Injection контейнер                | `require_once` для підключення залежностей        |
| Middleware-шар (фільтри запитів)              | `bootstrap.php` + CSRF-перевірка в контролерах    |
| ORM (Eloquent, SQLAlchemy)                    | Прямі PDO-запити з підготовленими виразами        |
| Interface / Abstract класи                    | Конкретні класи без інтерфейсів                   |

### Потік запиту

```
Браузер
  │ GET/POST
  ▼
Entry Script (напр. view.php)
  │ require_once
  ▼
bootstrap.php ← ініціалізація: сесії, БД, моделі, CSRF, getCurrentUser()
  │ виклик методу
  ▼
Controller ← валідація, координація
  │ делегування
  ▼
Service ← бізнес-логіка (кредити, авторизація, квести)
  │ виклик PDO
  ▼
Model ← CRUD-операції з БД
  │
  ▼
Template ← PHP+HTML рендеринг (header + page + footer)
  │
  ▼
HTML-відповідь → Браузер
```

Для API-запитів потік той самий, але замість Template повертається JSON.

```
Браузер / Зовнішній клієнт
  │ AJAX / REST
  ▼
api/*.php ← require bootstrap + аутентифікація (JWT або сесія)
  │
  ▼
Service / Model
  │
  ▼
JSON-відповідь → Клієнт
```

### 4.1 Компоненти

| Шар               | Файли                                                                                                                                      | Відповідальність                                                                                                   |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------ |
| **Entry Scripts** | `index.php`, `create.php`, `view.php`, `login.php`, `settings.php`, `verify.php`, `credits.php`                                            | Точки входу; підключають bootstrap, викликають контролер, рендерять шаблон                                         |
| **Bootstrap**     | `includes/bootstrap.php`                                                                                                                   | Ініціалізація: сесії, БД, моделі, CSRF, глобальні функції (`getCurrentUser()`, `redirect()`), лінива обробка черги |
| **Controllers**   | `includes/controllers/AuthController.php`, `PasteController.php`, `SettingsController.php`, `VerifyController.php`                         | Обробка POST/GET, валідація, виклик сервісів/моделей, редиректи                                                    |
| **Services**      | `includes/services/AuthService.php`, `CreditService.php`, `PasteService.php`, `AdQuestService.php`                                         | Бізнес-логіка: транзакції кредитів, створення паст, авторизація, рекламні квести                                   |
| **Models**        | `includes/models/User.php`, `Paste.php`, `Order.php`, `Transaction.php`, `Passkey.php`                                                     | OOP-класи для CRUD та бізнес-правил поверх БД                                                                      |
| **Templates**     | `templates/header.php`, `footer.php`, `home.php`, `create.php`, `paste_view.php`, `login.php`, `settings.php`, `verify.php`, `credits.php` | PHP-HTML шаблони (не Twig — чистий PHP)                                                                            |
| **API**           | `api/oauth.php`, `passkey.php`, `pastes.php`, `download.php`, `auth_token.php`, `check_order_status.php`                                   | JSON API для OAuth, WebAuthn, CRUD паст, завантаження файлів                                                       |
| **Webhooks**      | `api/webhooks/donatello.php`, `telegram_stars.php`, `verify_ad.php`                                                                        | Колбеки від платіжних систем та рекламного квесту                                                                  |

### 4.2 Діаграма потоку даних

![](./img/Data%20Flow%20Diagram.png)

Детальніше про потік даних: [docs/api.md](api.md), [docs/includes.md](includes.md).

---

## 5. Технологічний стек

| Категорія      | Технологія                                 |
| -------------- | ------------------------------------------ |
| Мова           | PHP 8.0+ (тестовано 8.2/8.3)               |
| БД             | MySQL 8+ / MariaDB (PDO, utf8mb4)          |
| UI             | Bootstrap 3 (ретро-стилізація)             |
| Frontend JS    | jQuery + Fetch API + vanilla JS            |
| Залежності     | **Немає Composer** — чистий PHP            |
| PHP-розширення | `pdo_mysql`, `openssl`, `curl`, `mbstring` |
| Веб-сервер     | Apache + `mod_rewrite` (або Nginx)         |

---

## 6. Структура каталогів

```
proj/
├── admin/              # Адмін-панель (див. docs/admin-cron-data.md)
├── api/                # REST API + OAuth + Webhooks
│   └── webhooks/       # Платіжні колбеки + рекламний квест
├── assets/             # Статичні ресурси
│   ├── css/style.css   # Кастомні стилі поверх Bootstrap 3
│   ├── js/app.js       # Клієнтська логіка
│   ├── js/passkey.js   # WebAuthn клієнт
│   └── img/            # Зображення
├── config/             # Конфігурація та SQL-схема (див. docs/config.md)
├── cron/               # Фонові скрипти: worker + cleanup (див. docs/admin-cron-data.md)
├── data/
│   ├── uploads/        # Файли користувачів (.htaccess захист)
│   └── logs/           # Логи worker-а
├── docs/               # Документація (цей файл та інші)
├── includes/
│   ├── bootstrap.php   # Центральна ініціалізація
│   ├── csrf.php        # CSRF + Remember Me
│   ├── jwt.php         # JWT-утиліти
│   ├── mailer.php      # Відправка email через Resend API
│   ├── Moderation.php  # Контент-модерація (локальна + OpenAI)
│   ├── RateLimiter.php # Анти-спам / брутфорс
│   ├── Queue.php       # MySQL-backed черга задач
│   ├── webauthn.php    # WebAuthn утиліти
│   ├── controllers/    # MVC-контролери (див. docs/templates.md)
│   ├── models/         # MVC-моделі (див. docs/includes.md)
│   └── services/       # Бізнес-сервіси (див. docs/includes.md)
├── templates/          # PHP-HTML шаблони (див. docs/templates.md)
├── index.php           # Головна сторінка (список публічних паст)
├── create.php          # Створення пасти
├── view.php            # Перегляд пасти
├── login.php           # Авторизація
├── credits.php         # Поповнення балансу
├── settings.php        # Налаштування профілю
└── verify.php          # Верифікація email
```

---

## 7. Екосистема авторизації

PayPaste підтримує **5 способів авторизації**:

| Спосіб                 | Механізм                                 | Файли                           |
| ---------------------- | ---------------------------------------- | ------------------------------- |
| **Email + пароль**     | `password_verify()` + `$_SESSION`        | `AuthController`, `login.php`   |
| **Remember Me**        | HMAC-токен в cookie, 14 днів             | `csrf.php`, `AuthService`       |
| **GitHub OAuth**       | OAuth2 code flow → профіль + email       | `api/oauth.php`                 |
| **Telegram Login**     | HMAC-SHA256 перевірка підпису Widget     | `api/oauth.php`                 |
| **WebAuthn / Passkey** | FIDO2: реєстрація ключа + аутентифікація | `api/passkey.php`, `passkey.js` |

### 7.1 Верифікація email

- Усі нові акаунти проходять обов'язкову верифікацію через OTP-код (6 цифр)
- Код відправляється через Resend API (через чергу `Queue::TYPE_EMAIL_VERIFY`)
- Термін дії коду: 10 хвилин
- Невірні спроби обмежені RateLimiter (5/хв)
- Після верифікації: `user.is_verified = 1`

### 7.2 OAuth-авторизація: сценарії

| Сценарій                    | Умова                                         | Дія                                                          |
| --------------------------- | --------------------------------------------- | ------------------------------------------------------------ |
| **Вхід / авто-реєстрація**  | `$_SESSION['user_id']` не встановлено         | `AuthService::oauthLogin()` → авто-реєстрація з 100 кредитів |
| **Прив'язка**               | `$_SESSION['user_id']` встановлено            | `AuthService::linkOAuth()` → прив'язка провайдера до акаунта |
| **Підтвердження видалення** | `confirm_delete_oauth` у GET + співпадіння ID | `$_SESSION['oauth_confirmed_delete'] = true`                 |

### 7.3 WebAuthn / Passkey

- Реєстрація: браузер генерує ключ → `api/passkey.php` зберігає в `passkeys` таблицю
- Аутентифікація: браузер підписує challenge → сервер верифікує
- Користувач може мати кілька passkey-ключів (різні пристрої)
- Видалення ключа через `settings.php`

### 7.4 Remember Me

- Cookie `remember_me` містить `user_id:hmac(user_id + password_hash)`
- При кожному запиті `checkRememberMe()` перевіряє HMAC та відновлює сесію
- Зміна пароля інвалідує cookie (HMAC залежить від `password_hash`)

### 7.5 Доступність функцій за статусом

| Функція                 | Гість | Верифікований | Невірифікований           |
| ----------------------- | ----- | ------------- | ------------------------- |
| Перегляд публічних паст | ✅    | ✅            | ✅                        |
| Авторизація             | ✅    | —             | —                         |
| Створення паст          | ❌    | ✅            | ❌                        |
| Купівля доступу         | ❌    | ✅            | ❌                        |
| API-доступ              | ❌    | ✅            | ❌                        |
| Зміна email             | ❌    | ✅            | ✅ (повторна верифікація) |

Неверифіковані користувачі редиректуються на `verify.php` при спробі створення/купівлі.

---

## 8. Економіка та бізнес-логіка

### 8.1 Кредити

Внутрішня валюта. 1 кредит ≈ умовна одиниця. Усі операції — через `CreditService` з `idempotency_key` для захисту від дублювання.

| Операція                | Зміна балансу | Умови                                         |
| ----------------------- | ------------- | --------------------------------------------- |
| Реєстрація              | +100          | Одноразово при створенні акаунта              |
| Створення платної пасти | −creation_fee | `ceil(довжина_вмісту / 10)`, мінімум 1 кредит |
| Купівля доступу         | −view_cost    | З покупця списується вартість пасти           |
| Продаж доступу          | +view_cost    | Автору зараховується вартість пасти           |
| Поповнення (topup)      | +сума         | Через Donatello або Telegram Stars            |
| Рекламний квест         | 0             | Безкоштовно через перегляд реклами            |
| API-запит               | −1            | Плата за кожен запит до REST API              |

### 8.2 Фінансові операції

Усі операції з кредитами виконуються через `CreditService` з обов'язковими PDO-транзакціями:

```php
$pdo->beginTransaction();
try {
    CreditService::deduct($buyer, $cost, 'purchase', ...);
    CreditService::credit($author, $cost, 'sale', ...);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}
```

### 8.3 Захист від race conditions

- Умовне списання: `UPDATE users SET credits = credits - ? WHERE credits >= ?` (атомарна операція)
- `idempotency_key` з UNIQUE-обмеженням у таблиці `transactions` — повторна операція з тим самим ключем ігнорується
- `SELECT ... FOR UPDATE` при критичних операціях

### 8.4 Поповнення балансу

1. Користувач ініціює поповнення → створюється `Order` (status: pending)
2. **Donatello**: редирект на сторінку оплати → webhook підтвердження → `CreditService::credit()`
3. **Telegram Stars**: відкриття бота → Invoice → `successful_payment` webhook → `CreditService::credit()`
4. Клієнт поллить статус через `api/check_order_status.php`

### 8.5 API-використання

Кожен запит до REST API (`api/pastes.php`) списує 1 кредит (тип: `api_usage`). Додатково списується `creation_fee` при створенні пасти та `view_cost` при доступі до платної.

---

## 9. Безпека

### 9.1 CSRF-захист

- Усі POST-форми містять `csrf_field()` — hidden input з токеном з сесії
- Контролери перевіряють через `verify_csrf()` перед обробкою POST
- AJAX-запити передають `csrf_token` у тілі запиту

### 9.2 SQL-ін'єкції

- Усі SQL-запити використовують підготовлені вирази (`prepare()` + `execute()`)
- Ніколи не використовується пряма інтерполяція змінних у SQL

### 9.3 XSS-захист

- Усі дані користувачів при виводі проходять через `htmlspecialchars()`
- Email-шаблони використовують текстовий формат (не HTML-тіло з user-даними)

### 9.4 Файлові завантаження

- Перевірка MIME-типу AND розширення файлу
- Файли зберігаються в `data/uploads/`, захищеному `.htaccess` (або Nginx deny)
- Завантаження через проксі `api/download.php` (перевірка доступу перед віддачею)

### 9.5 Rate Limiting

| Дія             | Ліміт          | Механізм       |
| --------------- | -------------- | -------------- |
| Логін           | 5 спроб / хв   | RateLimiter    |
| Реєстрація      | 3 / хв         | RateLimiter    |
| Створення пасти | 5 / хв         | RateLimiter    |
| Рекламний квест | 12 подій / 60с | AdQuestService |
| API-запити      | За кредитами   | 1 кредит/запит |

### 9.6 Аутентифікація

- Паролі: `password_hash()` / `password_verify()` (bcrypt)
- Сесії: `session_regenerate_id()` при логіні
- Remember Me: HMAC-токен, інвалідується при зміні пароля
- API: JWT (HS256) з терміном дії 7 днів
- WebAuthn: FIDO2 challenge-response

### 9.7 Відомі обмеження

- Telegram Stars webhook не верифікує підпис запиту (безпека забезпечується невідомістю URL — security through obscurity). **Потрібно додати `secret_token` верифікацію.**
- `.htaccess` захист `data/uploads/` працює лише з Apache; для Nginx потрібен окремий конфіг (див. `ops/nginx.example.conf`)
- Адмін-панель захищається лише перевіркою `is_admin` у сесії; окрема CSRF-захист відсутня в деяких формах

---

## 10. Черга задач та фонові процеси

### 10.1 Архітектура черги

Дворівнева система обробки фонових задач:

| Рівень                | Механізм              | Умова роботи                       |
| --------------------- | --------------------- | ---------------------------------- |
| **Inline Fallback**   | Усередині HTTP-запиту | Worker не запущений                |
| **Background Worker** | CLI `cron/worker.php` | Запущений як daemon або через cron |

Якщо worker запущений — задачі обробляються ним. Якщо ні — задача виконується синхронно під час HTTP-запиту (inline fallback).

### 10.2 Таблиця `jobs`

| Колонка           | Тип         | Опис                                           |
| ----------------- | ----------- | ---------------------------------------------- |
| `id`              | VARCHAR(50) | Унікальний ID (random_bytes)                   |
| `type`            | VARCHAR(50) | Тип задачі (див. нижче)                        |
| `status`          | ENUM        | `pending`, `processing`, `completed`, `failed` |
| `payload`         | JSON        | Дані задачі                                    |
| `attempts`        | INT         | Кількість спроб                                |
| `max_attempts`    | INT         | Максимум спроб (3)                             |
| `idempotency_key` | VARCHAR     | Унікальний ключ (захист від дублювання)        |
| `scheduled_at`    | TIMESTAMP   | Час планового виконання                        |

### 10.3 Типи задач

| Тип                  | Обробник               | Опис                           |
| -------------------- | ---------------------- | ------------------------------ |
| `moderation_check`   | Moderation::localCheck | Локальна перевірка контенту    |
| `moderation_rewrite` | AI rewrite             | AI-переписування контенту      |
| `email_verify`       | Mailer::sendVerify     | Відправка OTP-коду верифікації |
| `email_changed`      | Mailer::sendChanged    | Повідомлення про зміну email   |

### 10.4 Worker (`cron/worker.php`)

- Уніфікований обробник усіх типів задач
- Режими: `single` (одна ітерація) або `--daemon` (безкінний цикл)
- Фільтрація за типом: `--type=moderation_rewrite`
- Systemd-сервіс: `ops/worker/paypaste-worker.service`
- Логування: `data/logs/worker.log`

---

## 11. Модерація контенту

Дворівнева система модерації:

1. **Локальна перевірка** — `Moderation::localCheck()` — фільтр за `bad_words.json` (Регулярні вирази)
2. **AI-переписування** — при виявленні забороненого контенту, worker може переписати вміст через OpenAI/Ollama

Модерація запускається:

- При створенні пасти → `Queue::push('moderation_check')`
- Worker обробляє → якщо контент заборонений → `is_pending_rewrite = 1`, `moderation_status = 'flagged'`
- Після AI-переписування → `moderation_status = 'rewritten'`

---

## 12. Схема бази даних

Основні таблиці (повна схема: [docs/config.md](config.md)):

| Таблиця           | Призначення                  | Ключовий зв'язок            |
| ----------------- | ---------------------------- | --------------------------- |
| `users`           | Користувачі + баланс         | 1:N → усі інші              |
| `pastes`          | Пасти + налаштування доступу | N:1 → users                 |
| `paste_tags`      | Теги паст                    | N:1 → pastes                |
| `unlocked_pastes` | Куплений доступ (N:M)        | → users + pastes            |
| `orders`          | Замовлення на поповнення     | N:1 → users                 |
| `transactions`    | Історія операцій з кредитами | N:1 → users, pastes, orders |
| `passkeys`        | WebAuthn ключі               | N:1 → users                 |
| `rate_limits`     | Ліміти запитів               | N:1 → users                 |
| `ad_events`       | Рекламні події (квести)      | → users + pastes            |
| `jobs`            | Черга фонових задач          | Незалежна                   |

---

## 13. Інтеграції з зовнішніми сервісами

| Сервіс        | Тип                  | Конфігурація                                           |
| ------------- | -------------------- | ------------------------------------------------------ |
| **GitHub**    | OAuth2               | `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`             |
| **Telegram**  | Login Widget + Stars | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`          |
| **Donatello** | Платежі (fiat)       | `DONATELLO_URL`, `DONATELLO_SECRET`                    |
| **Resend**    | Email API            | `RESEND_API_KEY`                                       |
| **OpenAI**    | AI API               | `OPENAI_API_KEY`, `OPENAI_MODEL`                       |
| **Ollama**    | AI API (лок.)        | `OLLAMA_API_KEY`, `OLLAMA_URL`, `OLLAMA_MODEL`         |
| **Adsterra**  | Реклама              | `ADSTERRA_*` (Social Bar, Popunder, Smartlink, банери) |

---

## 14. Розгортання

### 14.1 Вимоги

- PHP 8.0+ з розширеннями: `pdo_mysql`, `openssl`, `curl`, `mbstring`
- MySQL 8+ / MariaDB
- Apache з `mod_rewrite` (або Nginx з конфігом з `ops/nginx.example.conf`)

### 14.2 Кроки встановлення

```bash
# 1. Клонування
git clone <repo-url> && cd paypaste

# 2. Імпорт схеми БД
mysql -u root -p < config/paypaste.sql

# 3. Конфігурація
cp config/config.example.php config/config.php
# Заповнити config.php: БД, ключі API, URL

# 4. Веб-сервер
# Apache: налаштувати DocumentRoot на корінь проекту (.htaccess вже є)
# Nginx: скопіювати ops/nginx.example.conf у /etc/nginx/sites-available/
```

### 14.3 Фонові процеси

```bash
# Worker — одноразово
php cron/worker.php

# Worker — daemon-режим
php cron/worker.php --daemon

# Worker — лише email-задачі
php cron/worker.php --type=email

# Cleanup (крон)
0 * * * * php /path/to/cron/cleanup.php --force >> /path/to/data/logs/cleanup.log 2>&1

# Worker через cron (якщо без systemd)
* * * * * php /path/to/cron/worker.php >> /path/to/data/logs/worker.log 2>&1
```

Детальніше: [docs/admin-cron-data.md](admin-cron-data.md).

---

## 15. Кодування: стандарти та конвенції

| Категорія           | Конвенція                                 |
| ------------------- | ----------------------------------------- |
| Файли класів        | `PascalCase.php`                          |
| Інші файли          | `snake_case.php`                          |
| Класи               | `PascalCase`                              |
| Методи/функції      | `camelCase`                               |
| Властивості моделей | `$snake_case`                             |
| Константи           | `SCREAMING_SNAKE_CASE`                    |
| Таблиці БД          | `snake_case`                              |
| Коментарі           | Українською                               |
| UI-повідомлення     | Українською                               |
| Макс. довжина рядка | ~120 символів                             |
| Відступи            | 4 пробіли або Tab (слідувати стилю файлу) |
| PHP теги            | Завжди `<?php`                            |

---

## 16. Навігація по документації

| Документ                                        | Зміст                                               |
| ----------------------------------------------- | --------------------------------------------------- |
| **[architecture.md](architecture.md)** ← ви тут | Загальний огляд, архітектура, безпека, економіка    |
| [config.md](config.md)                          | Конфігурація, константи, схема БД, підключення      |
| [includes.md](includes.md)                      | Моделі, сервіси, контролери, утиліти, бізнес-логіка |
| [templates.md](templates.md)                    | Шаблони, entry points, UI-потік, форми              |
| [api.md](api.md)                                | REST API, OAuth, WebAuthn, платіжні webhooks        |
| [admin-cron-data.md](admin-cron-data.md)        | Адмін-панель, Worker, cleanup, файлове сховище      |
| [assets.md](assets.md)                          | CSS, JS, статика, ретро-стилізація, WebAuthn клієнт |
