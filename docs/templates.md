# 📄 Шаблони (templates/) та Точки входу (Entry Points)

> Документація шаблонного шару PayPaste — HTML-представлень, що рендеряться з PHP, та кореневих скриптів, які їх підключають.
>
> **Для новачка:** шаблони — це те, що бачить користувач у браузері. Кожна сторінка = PHP-файл з HTML + PHP-кодом для виводу даних. Немає шаблонизатора (нашого Blade/Twig) — тільки чистий PHP: `<?php echo htmlspecialchars($var); ?>`. Entry points (кореневі .php-файли) — це «контролери-маршрутизатори», кожен відповідає за одну сторінку.

---

## Загальна архітектура рендерингу

Усі сторінки PayPaste будуються за єдиним патерном:

```
Entry Point (кореневий .php)
    │
    ├── require_once includes/bootstrap.php      // Сесії, DB, моделі, функції
    ├── require_once includes/controllers/XxxController.php
    ├── $controller->handleRequest()             // POST-обробка, валідація, редиректи
    │
    ├── (підготовка даних для шаблону)           // Запити до моделей, обчислення змінних
    │
    ├── require_once templates/header.php         // <head>, <nav>, повідомлення, реклама
    ├── require_once templates/{page}.php         // Контент сторінки
    └── require_once templates/footer.php         // </div>, <footer>, реклама, </body>
```

### Ключові принципи

| Принцип                       | Опис                                                                                         |
| ----------------------------- | -------------------------------------------------------------------------------------------- |
| **Контролер перед шаблоном**  | Усі POST-запити обробляються ДО підключення шаблонів — `handleRequest()` викликається першим |
| **Flash-повідомлення**        | `$_SESSION['error']` та `$_SESSION['success']` виводяться в `header.php` і одразу `unset()`  |
| **Старе введення**            | `$_SESSION['old_input']` зберігає дані форм при помилках валідації, очищається в шаблоні     |
| **Умовне приховання реклами** | Змінна `$hide_ads` (встановлюється в `view.php`) вимикає Adsterra-блоки для публічних паст   |
| **SEO-змінні**                | `$page_title` та `$page_description` передаються з entry point у `header.php`                |
| **Тема користувача**          | `header.php` читає `getCurrentUser()->theme` і встановлює `data-theme` на `<html>`           |
| **CSRF-захист**               | Усі POST-форми мають `<?= csrf_field() ?>`, перевірка — `verify_csrf()` у контролерах        |

---

## Точки входу (Entry Points)

### `index.php` — Головна сторінка

| Аспект          | Значення                                                |
| --------------- | ------------------------------------------------------- |
| **Контролер**   | `PasteController`                                       |
| **Шаблони**     | `header.php` → `home.php` → `footer.php`                |
| **Призначення** | Список публічних паст з фільтрацією за категорією/тегом |

**Потік даних:**

1. `PasteController::handleRequest()` — обробка POST (якщо є)
2. Підключення шаблонів напряму, без додаткової підготовки даних
3. `home.php` самостійно викликає `Paste::findAllPublic()` та `Paste::getPopularTags()`

---

### `create.php` — Створення пасти

| Аспект          | Значення                                             |
| --------------- | ---------------------------------------------------- |
| **Контролер**   | `PasteController`                                    |
| **Шаблони**     | `header.php` → `create.php` → `footer.php`           |
| **Призначення** | Форма створення нової пасти (текст, теги, файл, TTL) |

**Потік даних:**

1. `PasteController::handleRequest()` — обробка `action=create_paste`, `action=rewrite_and_publish`
2. При невдалій модерації: `$_SESSION['moderation_failed']` та `$_SESSION['flagged_categories']`
3. Шаблон читає `$_SESSION['old_input']` для відновлення даних форми

---

### `view.php` — Перегляд пасти

| Аспект          | Значення                                                |
| --------------- | ------------------------------------------------------- |
| **Контролер**   | `PasteController` (тільки для POST `unlock_paste`)      |
| **Сервіси**     | `PasteService`, `AdQuestService`                        |
| **Шаблони**     | `header.php` → `paste_view.php` → `footer.php`          |
| **Призначення** | Перегляд вмісту пасти, купівля доступу, рекламний квест |
| **Параметр**    | `$_GET['id']` — ID пасти                                |

**Потік даних (найскладніший entry point):**

1. POST → `PasteController::handleRequest()` → `unlock_paste`
2. GET → `Paste::findById($id)` — ленива перевірка TTL (видаляє прострочені)
3. Послідовні перевірки:
   - `isExpired()` → 404 з попередженням
   - `is_pending_rewrite` → 403 для не-авторів
   - `moderation_status === 'pending'` → 403 для не-авторів
   - `moderation_status === 'rejected'` → 403 для не-авторів
   - `is_private` → 403 для не-авторів та не-адмінів
4. Обчислення змінних доступу:
   - `$is_author` — чи є поточний користувач автором
   - `$has_unlocked` — чи купував доступ
   - `$ad_quest_progress` — прогрес рекламного квесту (0–3)
   - `$ad_quest_token` — серверний токен для валідації квесту
   - `$has_ad_access` — чи пройдено квест
   - `$requires_quest` — чи потрібен квест (платна + не автор + не куплено + не пройдено квест)
   - `$is_locked` — чи заблоковано контент (PasteService::isLocked)
   - `$hide_ads` — приховати рекламу для публічних паст

---

### `login.php` — Авторизація

| Аспект          | Значення                                  |
| --------------- | ----------------------------------------- |
| **Контролер**   | `AuthController`                          |
| **Шаблони**     | `header.php` → `login.php` → `footer.php` |
| **Призначення** | Вхід / Реєстрація / OAuth / Passkey       |

**Потік даних:**

1. `AuthController::handleRequest()` — обробка `login`, `register`, `logout`
2. Шаблон читає `$_GET['mode']` (login/register) для визначення режиму
3. OAuth-кнопки (GitHub, Telegram) ведуть на `api/oauth.php`

---

### `settings.php` — Налаштування профілю

| Аспект          | Значення                                                                           |
| --------------- | ---------------------------------------------------------------------------------- |
| **Контролер**   | `SettingsController`                                                               |
| **Шаблони**     | `header.php` → `settings.php` → `footer.php`                                       |
| **Призначення** | Профіль, пароль, тема, passkeys, OAuth-зв'язки, API-ключ, пасті, видалення акаунта |
| **Захист**      | Редірект на `login.php` якщо неавторизований                                       |

**Потік даних:**

1. Перевірка `getCurrentUser()` → редирект якщо `null`
2. `SettingsController::handleRequest()` — обробка усіх дій:
   - `update_profile` — нікнейм, email, пароль
   - `update_theme` — вибір кольорової теми
   - `generate_api_key` — генерація/перегенерація API-ключа
   - `toggle_visibility` — публічна/приватна паста
   - `delete_paste` — видалення пасти
   - `unlink_account` — від'єднання GitHub/Telegram
   - `delete_account` — видалення акаунта (з підтвердженням паролем/passkey/OAuth)
3. Шаблон самостійно завантажує: `User::findById()`, `Paste::findByUserId()`, `Passkey::findByUserId()`

---

### `verify.php` — Підтвердження email

| Аспект          | Значення                                   |
| --------------- | ------------------------------------------ |
| **Контролер**   | `VerifyController`                         |
| **Шаблони**     | `header.php` → `verify.php` → `footer.php` |
| **Призначення** | Введення OTP-коду, відправленого на email  |

**Потік даних:**

1. `VerifyController::handleRequest()` — обробка `verify_code`, `resend_code`
2. Шаблон читає `$user->email` (підготовлений контролером)
3. Flash-повідомлення через `$_SESSION['error_msg']` та `$_SESSION['success_msg']`

---

### `credits.php` — Поповнення балансу

| Аспект          | Значення                                        |
| --------------- | ----------------------------------------------- |
| **Моделі**      | `Order`                                         |
| **Шаблони**     | `header.php` → `credits.php` → `footer.php`     |
| **Призначення** | Тарифи, оплата через Donatello / Telegram Stars |
| **Захист**      | Редірект на `login.php` якщо неавторизований    |

**Потік даних:**

1. Перевірка `getCurrentUser()` → редирект якщо `null`
2. Генерація `order_id` (`order_` + md5-фрагмент)
3. Створення `Order` зі статусом `pending` та збереження в БД
4. Шаблон використовує `$user`, `$order_id` для рендерингу тарифів та кнопок оплати
5. JS-polling `api/check_order_status.php` кожні 3 сек після натискання кнопки оплати

---

## Шаблони (templates/)

### `header.php` — Глобальний хедер

**Призначення:** Початок HTML-документа, навігаційна панель, flash-повідомлення, верхня реклама.

#### Змінні що використовуються

| Змінна                     | Джерело                   | Опис                                                                             |
| -------------------------- | ------------------------- | -------------------------------------------------------------------------------- |
| `$page_title`              | Entry point               | SEO-заголовок сторінки (fallback: "PayPaste - Швидкий обмін кодом та текстом")   |
| `$page_description`        | Entry point               | SEO-опис сторінки (fallback: стандартний опис)                                   |
| `$hide_ads`                | Entry point (`view.php`)  | Якщо `true` — приховує усі Adsterra-блоки (для публічних паст)                   |
| `$_theme`                  | `getCurrentUser()->theme` | Кольорова тема користувача (retro/dark/terminal/light/github/retro-green) |
| `$_SESSION['error']`       | Контролер                 | Flash-повідомлення про помилку (виводиться і одразу `unset`)                     |
| `$_SESSION['success']`     | Контролер                 | Flash-повідомлення про успіх (виводиться і одразу `unset`)                       |
| `APP_URL`                  | `config.php`              | Базова URL-адреса для OpenGraph                                                  |
| `ADSTERRA_SOCIAL_BAR_URL`  | `config.php`              | URL скрипта Social Bar                                                           |
| `ADSTERRA_320x50_KEY`      | `config.php`              | Ключ банера 320×50                                                               |
| `ADSTERRA_INVOKE_BASE_URL` | `config.php`              | Базовий URL Adsterra invoke                                                      |

#### Структура

1. **`<head>`** — SEO-мета, OpenGraph, Bootstrap 3 CSS, кастомний `style.css`, jQuery, Adsterra Social Bar
2. **`<nav>`** — Навігаційна панель:
   - Лого "☠ PAYPASTE☠" з мигаючим текстом
   - Головна / Нова паста
   - Як авторизований: баланс кредитів, Поповнити, Налаштування (нік), Адмінка (як admin), Вихід
   - Як гість: Вхід / Реєстрація
3. **Банер 320×50** — мобільний лідерборд Adsterra
4. **Flash-повідомлення** — `alert-danger` / `alert-success`

#### CSS/JS залежності

- `bootstrap/3.4.1/css/bootstrap.min.css` (CDN)
- `assets/css/style.css` (локальний, з cache-bust `?v=filemtime`)
- `jquery/3.6.0.min.js` (CDN)
- Adsterra Social Bar (умовно)

---

### `footer.php` — Глобальний футер

**Призначення:** Завершення контейнера, футер з бейджами, рекламні блоки, підключення JS.

#### Змінні що використовуються

| Змінна                     | Джерело      | Опис                          |
| -------------------------- | ------------ | ----------------------------- |
| `$hide_ads`                | Entry point  | Приховування рекламних блоків |
| `ADSTERRA_300x250_KEY`     | `config.php` | Ключ банера 300×250           |
| `ADSTERRA_POPUNDER_URL`    | `config.php` | URL Popunder-скрипта          |
| `ADSTERRA_INVOKE_BASE_URL` | `config.php` | Базовий URL Adsterra invoke   |

#### Структура

1. `</div>` — закриття `.container.main-content`
2. `<footer>` — копірайт з Unix-часом, ретро-бейджі (HTML 4.01, PHP, NeoVim, IE6 Compatible, тощо)
3. Adsterra: банер 300×250 та Popunder (умовно)
4. JS: Bootstrap 3, `app.js`, `passkey.js`, `theme-switch.js`

#### CSS/JS залежності

- `bootstrap/3.4.1/js/bootstrap.min.js` (CDN)
- `assets/js/app.js` (локальний)
- `assets/js/passkey.js` (локальний, cache-bust `?v=1.4`)
- `assets/js/theme-switch.js` (локальний, cache-bust `?v=1.0`)

---

### `home.php` — Головна сторінка (список паст)

**Призначення:** Відображення списку публічних паст з фільтрацією за категорією та тегом, панель популярних тегів.

#### Змінні що використовуються

| Змінна              | Джерело                        | Опис                                 |
| ------------------- | ------------------------------ | ------------------------------------ |
| `$_GET['tag']`      | URL-параметр                   | Фільтр за тегом                      |
| `$_GET['category']` | URL-параметр (fallback: 'all') | Фільтр: all/paid/free/user/anonymous |
| `$pastes`           | `Paste::findAllPublic()`       | Масив публічних паст (ліміт 20)      |
| `$popularTags`      | `Paste::getPopularTags(15)`    | 15 найпопулярніших тегів             |

#### Форми

Форми відсутні — тільки GET-посилання для навігації.

#### Елементи інтерфейсу

- **Кнопки категорій**: Усі / Платні / Безплатні / Користувацькі / Анонімні
- **Список паст**: кожна — заголовок, дата, ціна (як платна), теги (перші 3 + розгортання)
- **Панель тегів**: 15 популярних тегів з лічильниками, кольори через `Paste::getTagColor()`
- **Реклама**: Adsterra 160×300 у бічній панелі

#### CSS/JS залежності

- Inline jQuery-скрипт для `.toggle-tags` (розгортання додаткових тегів)
- Adsterra 160×300 банер

---

### `create.php` — Форма створення пасти

**Призначення:** Форма створення нової пасти з усіма налаштуваннями, попередження про невдалу модерацію з кнопкою AI-переписування.

#### Змінні що використовуються

| Змінна                            | Джерело                  | Опис                                            |
| --------------------------------- | ------------------------ | ----------------------------------------------- |
| `$old`                            | `$_SESSION['old_input']` | Попередні значення форми при помилках валідації |
| `$_SESSION['moderation_failed']`  | Контролер                | Ознака невдалої модерації (виводить банер)      |
| `$_SESSION['flagged_categories']` | Контролер                | Категорії порушень модерації                    |
| `$_SESSION['user_id']`            | Сесія                    | ID користувача (для обчислення ліміту символів) |

#### Форми

**1. Основна форма** (`action="create.php"`, `method="POST"`, `enctype="multipart/form-data"`):

| Поле           | name         | Тип      | Опис                                     |
| -------------- | ------------ | -------- | ---------------------------------------- |
| CSRF           | —            | hidden   | `csrf_field()`                           |
| Дія            | `action`     | hidden   | `create_paste`                           |
| Текст          | `content`    | textarea | Основний вміст пасти (monospace)         |
| Назва          | `title`      | text     | Опціонально                              |
| Файл           | `attachment` | file     | До 5 МБ, accept: png/jpg/gif/pdf/zip/txt |
| Мова           | `language`   | select   | 13 мов (plaintext → bash)                |
| Теги           | `tags`       | text     | Через кому або пробіл                    |
| Приватна       | `is_private` | checkbox | 0/1                                      |
| Платна         | `is_paid`    | checkbox | 0/1, показує `view_cost_container`       |
| Ціна перегляду | `view_cost`  | number   | Мінімум 1, обов'язкове якщо `is_paid`    |
| Час життя      | `expires_in` | select   | 0/10/30/60/360/1440/10080 хвилин         |

**2. Форма AI-переписування** (`action="create.php"`, `method="POST"`):

| Поле       | name         | Тип    | Опис                      |
| ---------- | ------------ | ------ | ------------------------- |
| CSRF       | —            | hidden | `csrf_field()`            |
| Дія        | `action`     | hidden | `rewrite_and_publish`     |
| content    | `content`    | hidden | Текст з `$old['content']` |
| title      | `title`      | hidden | Назва з `$old['title']`   |
| is_private | `is_private` | hidden | 0/1                       |
| is_paid    | `is_paid`    | hidden | 0/1                       |
| view_cost  | `view_cost`  | hidden | Ціна                      |
| expires_in | `expires_in` | hidden | TTL                       |

#### CSS/JS залежності

- Inline JS-скрипт: динамічне керування `maxlength` textarea та видимістю `view_cost_container` залежно від чекбоксу `is_paid`
- Обчислення макс. символів: `User::findById()->credits * 10`

---

### `login.php` — Форми входу / реєстрації

**Призначення:** Комбінована сторінка авторизації з перемиканням між режимами входу та реєстрації.

#### Змінні що використовуються

| Змінна                       | Джерело                  | Опис                                    |
| ---------------------------- | ------------------------ | --------------------------------------- |
| `$old`                       | `$_SESSION['old_input']` | Попередні значення форми                |
| `$_COOKIE['remember_email']` | Cookie                   | Збережений email для поля входу         |
| `$_COOKIE['remember_me']`    | Cookie                   | Чи була галка "Запам'ятати"             |
| `$_GET['mode']`              | URL-параметр             | `login` або `register` (визначає режим) |
| `APP_URL`                    | `config.php`             | URL для OAuth-редиректу Telegram        |

#### Форми

**1. Форма авторизації** (`action="login.php"`, `method="POST"`, `id="auth-form"`):

| Поле                 | name               | Тип      | Режим      | Опис                   |
| -------------------- | ------------------ | -------- | ---------- | ---------------------- |
| CSRF                 | —                  | hidden   | Обидва     | `csrf_field()`         |
| E-mail               | `email`            | email    | Обидва     | Обов'язкове            |
| Нік                  | `nickname`         | text     | Реєстрація | `.reg-only`            |
| Пароль               | `password`         | password | Обидва     | Обов'язкове            |
| Підтвердження паролю | `password_confirm` | password | Реєстрація | `.reg-only`            |
| Запам'ятати          | `remember`         | checkbox | Вхід       | `.login-only`, 14 днів |
| Дія (реєстрація)     | `action`           | submit   | Реєстрація | value=`register`       |
| Дія (вхід)           | `action`           | submit   | Вхід       | value=`login`          |

**OAuth-кнопки:**

- GitHub → `api/oauth.php?provider=github`
- Telegram → Telegram Login Widget (`@PayPasteBot`)

**Passkey-кнопки:**

- Увійти через Passkey → `loginWithPasskey()` (з `passkey.js`)
- Зареєструватись через Passkey → `registerPasskey()` (з `passkey.js`)

#### CSS/JS залежності

- Inline JS: `switchToLogin()` / `switchToRegister()` — перемикання видимості `.reg-only` / `.login-only`, оновлення URL через `history.replaceState`
- Telegram Login Widget (`telegram-widget.js`)
- `passkey.js` — WebAuthn/FIDO2 реєстрація та вхід

---

### `paste_view.php` — Перегляд пасти

**Призначення:** Відображення вмісту пасти або повідомлення про блокування, оплату, модерацію, рекламний квест.

#### Змінні що використовуються

| Змінна               | Джерело    | Опис                                            |
| -------------------- | ---------- | ----------------------------------------------- |
| `$paste`             | `view.php` | Об'єкт пасти (може бути `null` при 404)         |
| `$is_author`         | `view.php` | Чи є поточний користувач автором                |
| `$has_unlocked`      | `view.php` | Чи купував користувач доступ                    |
| `$is_locked`         | `view.php` | Чи заблоковано контент (PasteService::isLocked) |
| `$requires_quest`    | `view.php` | Чи потрібен рекламний квест для доступу         |
| `$ad_quest_progress` | `view.php` | Прогрес квесту (0–3)                            |
| `$ad_quest_token`    | `view.php` | Серверний токен для валідації квесту            |
| `$hide_ads`          | `view.php` | Приховування реклами для публічних паст         |

#### Стан рендерингу (умовні гілки)

Шаблон має 6 основних станів:

| Стан                       | Умова                              | Що відображається                           |
| -------------------------- | ---------------------------------- | ------------------------------------------- |
| **404**                    | `$paste === null`                  | Alert: "Пасту не знайдено або її вкрали"    |
| **Протермінована**         | `$paste->isExpired()`              | Alert: час закінчився, кнопка "На головну"  |
| **AI-переписування**       | `$paste->is_pending_rewrite`       | Blink-анімація "ШІ ПРАЦЮЄ...", progress bar |
| **Модерація (очікування)** | `moderation_status === 'pending'`  | "МОДЕРАЦІЯ...", progress bar                |
| **Модерація (відхилення)** | `moderation_status === 'rejected'` | Причини відхилення, посилання на create.php |
| **Заблоковано (платна)**   | `$is_locked`                       | Кнопка "Купити доступ за N КР"              |
| **Рекламний квест**        | `$requires_quest`                  | Кнопка купити + квест 3×10с + progress bar  |
| **Доступ відкритий**       | Все інше                           | Контент з підсвіткою, теги, файл, реклама   |

#### Форми

**1. Купівля доступу** (`action="view.php"`, `method="POST"`):

| Поле     | name       | Тип    | Опис           |
| -------- | ---------- | ------ | -------------- |
| CSRF     | —          | hidden | `csrf_field()` |
| Дія      | `action`   | hidden | `unlock_paste` |
| Paste ID | `paste_id` | hidden | ID пасти       |

Ця форма дублюється у двох місцях: у банері `$is_locked` та у секції квесту `$requires_quest`.

#### Елементи інтерфейсу (при відкритому доступі)

- **Кнопка копіювання** — `#copy-btn` / `#paste-textarea` (hidden textarea для копіювання)
- **Контент з підсвіткою** — highlight.js (`github-dark` тема), `<pre><code class="hljs language-{lang}">`
- **Теги** — кольорові кнопки-посилання на `index.php?tag=...`
- **Прикріплений файл** — зображення (inline) або кнопка завантаження; URL через `api/download.php?id={paste_id}`
- **Реклама** — Adsterra 300×250 після контенту (умовно)

#### CSS/JS залежності

- `highlight.js/11.9.0` CSS + JS (CDN) — підсвітка синтаксису
- `hljs.highlightAll()` — автоматична підсвітка
- Inline JS: кнопка копіювання, рекламний квест таймер

---

### `settings.php` — Налаштування профілю

**Призначення:** Повний керуючий центр користувача: профіль, тема, passkeys, OAuth-зв'язки, список паст, API-ключ, видалення акаунта.

#### Змінні що використовуються

| Змінна                                  | Джерело                                | Опис                              |
| --------------------------------------- | -------------------------------------- | --------------------------------- |
| `$user`                                 | `User::findById($_SESSION['user_id'])` | Поточний користувач               |
| `$myPastes`                             | `Paste::findByUserId()`                | Список паст користувача           |
| `$myPasskeys`                           | `Passkey::findByUserId()`              | Список зареєстрованих passkeys    |
| `$_SESSION['passkey_confirmed_delete']` | Passkey-підтвердження                  | Підтвердження особи для видалення |
| `$_SESSION['oauth_confirmed_delete']`   | OAuth-підтвердження                    | Підтвердження особи для видалення |

#### Форми (7 форм на сторінці)

| #   | Дія (name="action") | Опис                                   |
| --- | ------------------- | -------------------------------------- |
| 1   | `update_profile`    | Нікнейм, email, пароль, підтвердження  |
| 2   | `update_theme`      | Вибір кольорової теми (6 радіо-кнопок) |
| 3   | `unlink_account`    | Від'єднання GitHub/Telegram            |
| 4   | `toggle_visibility` | Публічна ↔ Приватна (для кожної пасти) |
| 5   | `delete_paste`      | Видалення пасти (з confirm)            |
| 6   | `generate_api_key`  | Генерація / перегенерація API-ключа    |
| 7   | `delete_account`    | Видалення акаунта (з підтвердженням)   |

#### Секції сторінки

1. **Профіль** — нікнейм, email (з попередженням про повторне підтвердження), зміна пароля
2. **Тема сайту** — 6 карток (Retro, Dark, Terminal, Light, GitHub Orange, Retro Green) з міні-прев'ю та миттєвим попереднім переглядом
3. **Паскеї (Passkeys)** — список (до 5), кнопка "Додати", кнопки "Видалити" (макс. 5 passkeys)
4. **Пов'язані акаунти** — GitHub ID (прив'язати/від'єднати), Telegram ID (прив'язати через виджет/від'єднати)
5. **Мої пасти** — таблиця (назва, статус, ціна, дата, дії: розкрити/сховати/видалити)
6. **API налаштування** — відображення ключа (readonly), копіювання, генерація, міні-документація API
7. **Небезпечна зона** — видалення акаунта з підтвердженням (пароль / passkey / GitHub / Telegram)

#### CSS/JS залежності

- Inline JS: `copyApiKey()` — копіювання API-ключа в буфер
- `passkey.js` — `registerPasskey()`, `deletePasskey()`, `confirmDeleteAccountPasskey()`
- `theme-switch.js` — миттєвий попередній перегляд теми (через `data-theme` на `<html>`)
- Telegram Login Widget — для прив'язки Telegram акаунта

---

### `verify.php` — Підтвердження email

**Призначення:** Введення 6-значного OTP-коду, надісланого на email користувача.

#### Змінні що використовуються

| Змінна                     | Джерело   | Опис                     |
| -------------------------- | --------- | ------------------------ |
| `$user->email`             | Контролер | Email для відображення   |
| `$_SESSION['error_msg']`   | Контролер | Повідомлення про помилку |
| `$_SESSION['success_msg']` | Контролер | Повідомлення про успіх   |

#### Форми

**1. Верифікація коду** (`action="verify.php"`, `method="POST"`):

| Поле | name     | Тип    | Опис                   |
| ---- | -------- | ------ | ---------------------- |
| CSRF | —        | hidden | `csrf_field()`         |
| Дія  | `action` | hidden | `verify_code`          |
| Код  | `code`   | text   | 6-значний, maxlength=6 |

**2. Повторна відправка** (`action="verify.php"`, `method="POST"`):

| Поле | name     | Тип    | Опис           |
| ---- | -------- | ------ | -------------- |
| CSRF | —        | hidden | `csrf_field()` |
| Дія  | `action` | hidden | `resend_code`  |

#### Стилістика

Шаблон має виражений terminal/retro-стиль:

- Жовтий `panel-heading` з чорним текстом
- Монопросторовий шрифт, чорний фон
- Зелений текст для поля вводу коду
- Квадратні дужки в заголовках `[ ПІДТВЕРДЖЕННЯ ПОШТИ ]`

---

### `credits.php` — Поповнення балансу

**Призначення:** Тарифи кредитів, оплата через Donatello (фіат) або Telegram Stars (крипто-зірки), polling-перевірка статусу.

#### Змінні що використовуються

| Змінна          | Джерело                   | Опис                                        |
| --------------- | ------------------------- | ------------------------------------------- |
| `$user`         | `getCurrentUser()`        | Об'єкт користувача (баланс)                 |
| `$order_id`     | Сгенеровано в entry point | Унікальний ID замовлення (`order_XXXXXXXX`) |
| `DONATELLO_URL` | `config.php`              | URL сторінки Donatello для оплати           |

#### Тарифи

| Тариф          | Кредити | Ціна (UAH) | Telegram Stars |
| -------------- | ------- | ---------- | -------------- |
| 🥉 Базовий     | 100     | 25 ₴       | 50 ⭐          |
| 🥈 Стандартний | 500     | 100 ₴      | 200 ⭐         |
| 🥇 Преміум     | 1500    | 250 ₴      | 500 ⭐         |

#### Модальні вікна

1. **`#successModal`** — Успішна оплата: кількість зарахованих кредитів, новий баланс, автоперехід через 10 сек
2. **`#donatelloModal`** — Попередження: обов'язкове вказування `order_id` у повідомленні Donatello, кнопка копіювання коду

#### CSS/JS залежності

- Inline JS (значний обсяг):
  - Обробник кнопки Donatello → `$('#donatelloModal').modal('show')`
  - `copyOrderCode()` — копіювання коду замовлення
  - Polling-система: `$.getJSON('api/check_order_status.php')` кожні 3 сек
  - Обробка статусу `completed` → показ `#successModal`, зворотній відлік 10 сек → редирект на `index.php`

---

### `email_verify.html` — Шаблон листа підтвердження email

**Призначення:** HTML-шаблон листа, що надсилається через Resend API з OTP-кодом.

#### Плейсхолдер

| Плейсхолдер | Опис                            |
| ----------- | ------------------------------- |
| `{{CODE}}`  | 6-значний OTP-код підтвердження |

#### Використання

Шаблон читається в `includes/mailer.php`, плейсхолдер `{{CODE}}` замінюється на реальний код через `str_replace()`.

#### Стилістика

- Нейтральний форматування (не retro) — стандартний email-стиль
- Жовта верхня рамка `border-top: 4px solid #ffcc00` (кольори PayPaste)
- Код відображається великим шрифтом з літерним інтервалом

---

### `email_changed.html` — Шаблон листа про зміну email

**Призначення:** HTML-шаблон листа-сповіщення про зміну email-адреси акаунта.

#### Плейсхолдер

| Плейсхолдер     | Опис              |
| --------------- | ----------------- |
| `{{NEW_EMAIL}}` | Нова email-адреса |

#### Використання

Шаблон читається в `includes/mailer.php`, плейсхолдер `{{NEW_EMAIL}}` замінюється на нову адресу.

#### Стилістика

- Червона верхня рамка `border-top: 4px solid #d9534f` (попереджувальний стиль)
- Заклик звернутися до підтримки при несанкціонованій зміні

---

## Зв'язки між шаблонами та іншими компонентами

### Залежності від моделей

| Шаблон           | Моделі що використовуються                                                        |
| ---------------- | --------------------------------------------------------------------------------- |
| `home.php`       | `Paste` (`findAllPublic`, `getPopularTags`, `getTagColor`, `getTagsByPopularity`) |
| `create.php`     | `User` (`findById` — для обчислення ліміту символів)                              |
| `paste_view.php` | `Paste` (`getTags`, `getTagColor`), `glob()` для файлів                           |
| `settings.php`   | `User`, `Paste`, `Passkey`                                                        |
| `credits.php`    | — (використовує лише `$user` та `$order_id`)                                      |

### Залежності від контролерів

| Entry Point    | Контролер            | Дії що обробляються                                                                                                           |
| -------------- | -------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `index.php`    | `PasteController`    | (немає POST-дій на головній)                                                                                                  |
| `create.php`   | `PasteController`    | `create_paste`, `rewrite_and_publish`                                                                                         |
| `view.php`     | `PasteController`    | `unlock_paste`                                                                                                                |
| `login.php`    | `AuthController`     | `login`, `register`, `logout`                                                                                                 |
| `settings.php` | `SettingsController` | `update_profile`, `update_theme`, `generate_api_key`, `toggle_visibility`, `delete_paste`, `unlink_account`, `delete_account` |
| `verify.php`   | `VerifyController`   | `verify_code`, `resend_code`                                                                                                  |
| `credits.php`  | — (немає контролера) | Замовлення створюється напряму в entry point                                                                                  |

### Залежності від API

| Шаблон           | API-endpoint                                             | Опис                                   |
| ---------------- | -------------------------------------------------------- | -------------------------------------- |
| `credits.php`    | `api/check_order_status.php`                             | Polling статусу оплати                 |
| `paste_view.php` | `api/webhooks/verify_ad.php`                             | Підтвердження перегляду реклами        |
| `paste_view.php` | `api/download.php?id={paste_id}`                         | Проксі-завантаження файлів             |
| `login.php`      | `api/oauth.php?provider=github`                          | GitHub OAuth                           |
| `login.php`      | `api/oauth.php?provider=telegram`                        | Telegram OAuth                         |
| `settings.php`   | `api/oauth.php?provider=github&confirm_delete_oauth=1`   | Підтвердження видалення через GitHub   |
| `settings.php`   | `api/oauth.php?provider=telegram&confirm_delete_oauth=1` | Підтвердження видалення через Telegram |
| `settings.php`   | `api/passkey.php`                                        | WebAuthn реєстрація/видалення          |

### Залежності від сервісів

| Шаблон           | Сервіс           | Методи                                                        |
| ---------------- | ---------------- | ------------------------------------------------------------- |
| `paste_view.php` | `PasteService`   | `isLocked()` — визначення чи заблоковано контент              |
| `paste_view.php` | `AdQuestService` | `progress()`, `issueToken()`, `hasAccess()` — рекламний квест |

### Глобальні константи з config.php

| Константа                  | Використання в шаблонах                                               |
| -------------------------- | --------------------------------------------------------------------- |
| `APP_URL`                  | `header.php` (OpenGraph), `login.php` (OAuth), `settings.php` (OAuth) |
| `ADSTERRA_SOCIAL_BAR_URL`  | `header.php`                                                          |
| `ADSTERRA_320x50_KEY`      | `header.php`                                                          |
| `ADSTERRA_300x250_KEY`     | `footer.php`, `paste_view.php`                                        |
| `ADSTERRA_160x300_KEY`     | `home.php`                                                            |
| `ADSTERRA_POPUNDER_URL`    | `footer.php`                                                          |
| `ADSTERRA_INVOKE_BASE_URL` | `header.php`, `footer.php`, `home.php`, `paste_view.php`              |
| `ADSTERRA_SMARTLINK_URL`   | `paste_view.php` (квест-кнопка)                                       |
| `DONATELLO_URL`            | `credits.php`                                                         |

---

## Діаграма переходів між сторінками

![](./img/Page%20transition%20diagram.png)

---

## Adsterra-реклама: матриця розміщення

| Банер              | Розмір  | header | home | paste_view | footer | Умова показу |
| ------------------ | ------- | ------ | ---- | ---------- | ------ | ------------ |
| Social Bar         | —       | ✅     | —    | —          | —      | `!$hide_ads` |
| Mobile Leaderboard | 320×50  | ✅     | —    | —          | —      | `!$hide_ads` |
| Side Banner        | 160×300 | —      | ✅   | —          | —      | Завжди       |
| After Content      | 300×250 | —      | —    | ✅         | —      | `!$hide_ads` |
| Footer Banner      | 300×250 | —      | —    | —          | ✅     | `!$hide_ads` |
| Popunder           | —       | —      | —    | —          | ✅     | `!$hide_ads` |

> **Примітка:** `$hide_ads` встановлюється лише в `view.php` для публічних паст (`!is_paid && !is_private`). Усі інші сторінки показують рекламу.

---

## Теми користувача

Шаблон `settings.php` пропонує 6 кольорових тем, які застосовуються через CSS-змінні та атрибут `data-theme` на `<html>`:

| Тема             | Ключ конфігурації | Опис стилю                                              |
| ---------------- | ----------------- | ------------------------------------------------------- |
| ☠ Retro          | `retro`           | Жовтий акцент, сірий фон, Comic Sans (за замовчуванням) |
| 🌑 Dark          | `dark`            | Фіолетовий акцент, темний фон                           |
| 💻 Terminal      | `terminal`        | Зелений акцент на чорному, монохромний                  |
| ☀️ Light         | `light`           | Класичний світлий, синій акцент                         |
| 🐙 GitHub Dark    | `github`          | Темний фон, синій акцент                           |
| 💚 Retro Green   | `retro-green`     | Зелений акцент, світло-зелений фон                      |

Тема зберігається в `users.theme` та читається в `header.php` при кожному запиті.

---

## Перехресні посилання

| Пов'язаний документ                | Що деталізує                                            |
| ---------------------------------- | ------------------------------------------------------- |
| [includes.md](includes.md)         | Контролери та моделі що підживлюють шаблони             |
| [api.md](api.md)                   | API-endpoint'и що викликаються з шаблонів (AJAX, OAuth) |
| [assets.md](assets.md)             | CSS та JS що використовуються у шаблонах                |
| [config.md](config.md)             | Константи Adsterra, URL, WebAuthn що рендеряться у HTML |
| [architecture.md](architecture.md) | Загальний огляд архітектури та екосистеми               |
