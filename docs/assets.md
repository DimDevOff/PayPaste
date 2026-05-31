# 📁 assets/ — Статичні ресурси фронтенду

> Документація по папці `assets/` проекту PayPaste.
> Містить CSS-стилі, JavaScript-логіку та зображення.
>
> **Для новачка:** тут знаходиться все що бачить браузер без обробки сервером — стилі (6 тем оформлення, Bootstrap 3), скрипти (копіювання пасти, рекламний квест, WebAuthn), лого. Файли не містять бізнес-логіки — тільки відображення та клієнтська взаємодія.

---

## Загальна структура

```
assets/
├── css/
│   └── style.css          # Основні стилі проекту (повне перевизначення Bootstrap 3)
├── js/
│   ├── app.js             # Загальна клієнтська логіка (копіювання, квест реклами)
│   ├── passkey.js         # WebAuthn/FIDO2 клієнт (реєстрація, вхід, видалення passkey)
│   └── theme-switch.js    # Миттєвий перемикач тем (прев'ю без перезавантаження)
└── img/
    └── logo.png           # Логотип PayPaste (686×686, RGBA, ~425 KB)
```

### Підключення (header.php / footer.php)

| Ресурс             | Де підключається   | Примітка                          |
| ------------------ | ------------------ | --------------------------------- |
| jQuery 3.6.0       | `header.php` (CDN) | Залежність для `app.js`           |
| Bootstrap 3.4.1 JS | `footer.php` (CDN) | Підключається після jQuery        |
| `style.css`        | `header.php`       | З кеш-бастингом `?v=filemtime`    |
| `app.js`           | `footer.php`       | Після Bootstrap JS                |
| `passkey.js`       | `footer.php`       | Версія `?v=1.4`                   |
| `theme-switch.js`  | `footer.php`       | Версія `?v=1.0`                   |
| `logo.png`         | `header.php`       | favicon + navbar brand + og:image |

---

## 🎨 css/style.css — Основні стилі

### Філософія

CSS побудований на **CSS Custom Properties** (змінних), що дозволяє миттєво перемикати кольорові теми без перезавантаження сторінки. Усі кольори, фони та рамки посилаються на `var(--...)`, а не на хардкод. Базовий фреймворк — **Bootstrap 3**, але його стандартні кольори повністю перевизначені через `!important`.

### Кольорові теми (6 штук)

Тема вмикається через атрибут `data-theme` на `<html>` (встановлюється PHP з `$_SESSION` / `$_COOKIE`):

| Атрибут `data-theme`       | Назва              | Стилістика                                     | Акцентний колір          |
| -------------------------- | ------------------ | ---------------------------------------------- | ------------------------ |
| `retro` (за замовчуванням) | Retro              | Класичний сірий стиль 2000-х піратських сайтів | `#ffcc00` (жовтий)       |
| `dark`                     | Dark               | Темна з фіолетовим акцентом                    | `#9b59b6` (фіолетовий)   |
| `terminal`                 | Terminal           | Хакерський CRT-стиль, зелений на чорному       | `#00ff00` (зелений)      |
| `light`                    | Light              | Чисто-білий мінімалістичний                    | `#0066cc` (синій)        |
| `github-orange`            | GitHub Orange Dark | Темна з помаранчевим акцентом                  | `#FF9000` (помаранчевий) |
| `retro-green`              | Retro Green        | Зелена ретро-тема, як старі BBS                | `#4a8c1c` (зелений)      |

### CSS-змінні (повний список)

Кожна тема перевизначає однаковий набір змінних:

| Змінна                                                          | Призначення                                |
| --------------------------------------------------------------- | ------------------------------------------ |
| `--bg-primary`                                                  | Основний фон сторінки                      |
| `--bg-secondary`                                                | Фон панелей, карток, list-group            |
| `--text-primary`                                                | Основний текст                             |
| `--text-secondary`                                              | Другорядний текст                          |
| `--text-muted`                                                  | Приглушений текст                          |
| `--accent`                                                      | Акцентний колір (кнопки, заголовки, лінки) |
| `--accent-hover`                                                | Hover-стан акценту                         |
| `--accent-text`                                                 | Текст на акцентному фоні                   |
| `--border-color`                                                | Рамки панелей, інпутів                     |
| `--panel-header-bg`                                             | Фон заголовка панелі                       |
| `--panel-header-text`                                           | Текст заголовка панелі                     |
| `--panel-body-bg`                                               | Фон тіла панелі                            |
| `--navbar-bg`                                                   | Фон навбара                                |
| `--navbar-text`                                                 | Текст навбара (`.navbar-text`)             |
| `--navbar-link`                                                 | Посилання навбара                          |
| `--footer-bg`                                                   | Фон футера                                 |
| `--footer-text`                                                 | Текст футера                               |
| `--link-color`                                                  | Колір посилань                             |
| `--ad-bg` / `--ad-border` / `--ad-text`                         | Рекламний банер                            |
| `--btn-accent-bg` / `--btn-accent-text` / `--btn-accent-border` | Кнопка акценту (`.btn-warning`)            |
| `--table-header-bg` / `--table-header-text`                     | Заголовок таблиці                          |
| `--input-bg` / `--input-text` / `--input-border`                | Поля форм                                  |
| `--code-bg`                                                     | Фон `code`                                 |
| `--panel-info-bg` / `--panel-info-border`                       | Інформаційна панель                        |
| `--panel-danger-bg` / `--panel-danger-border`                   | Панель небезпеки                           |
| `--success` / `--danger`                                        | Статусні кольори                           |

### Перевизначення компонентів Bootstrap 3

Усі перевизначення використовують `!important` для перемагання над Bootstrap:

- **Панелі** (`.panel-default`, `.panel-primary`, `.panel-info`…) — заголовок + тіло
- **Кнопки** — `.btn-default` (hover → accent), `.btn-warning` (= accent), `.btn-danger`, `.btn-success`
- **Навбар** — `.navbar-inverse` повністю перекрашений
- **Пагінація** — сторінки + active-стан
- **Форми** — `.form-control`, `input[]`, `textarea`, `select` + readonly/disabled + focus
- **Алерти** — `.alert-success`, `.alert-info`, `.alert-warning`, `.alert-danger`
- **Таблиці** — `<thead>` + адаптивні стилі
- **Текстові статуси** — `.text-success`, `.text-danger`, `.text-info`, `.text-warning`, `.text-muted`, `.text-primary`
- **Модальні вікна** — `.modal-content`, `.modal-header`, `.modal-footer`
- **Code** — inline `code` (з рамкою), `pre code` (без стилів, для Prism/highlight.js)
- **List-group** — елементи + hover

### Кастомні класи

| Клас                 | Призначення                                           |
| -------------------- | ----------------------------------------------------- |
| `.ad-banner`         | Рекламний банер з пунктирною рамкою (`dashed`)        |
| `.block-info`        | Інформаційний блок (dashed border + accent-заголовок) |
| `.block-danger`      | Блок попередження (червоний dashed)                   |
| `.blink-text`        | Анімація блимання (3s infinite, 50% → opacity: 0)     |
| `.main-content`      | Flex-розтягнення контенту (`flex: 1 0 auto`)          |
| `.old-school-badges` | Контейнер бейджів у футері (flex-wrap, center)        |

### Адаптивність (Responsive)

**Підтримувані розміри:** від 320px до 1920px+

| Брейкпоінт                              | Що змінюється                                                                                                                                                                                                                                                                    |
| --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `≤ 767px` (`@media (max-width: 767px)`) | Контейнер без зайвих padding; `.panel-title` 14px; кнопки 12px + `white-space: normal`; таблиці `word-break: break-word`, 11px шрифт; навбар-елементи центруються; форми повна ширина; `.btn-block-mobile`, `.pull-right-mobile`, `.input-group-mobile` — утиліти для мобільного |
| `≤ 480px` (`@media (max-width: 480px)`) | `h1` 1.5em, `h2` 1.3em, `h3` 1.1em; банер 0.8em; бейджі 72×25px; textarea min-height 150px                                                                                                                                                                                       |

### Плавні переходи

Усі основні елементи мають `transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease` — це забезпечує плавне перемикання тем без ривків.

---

## 📜 js/app.js — Загальна клієнтська логіка

### Залежності

- **jQuery 3.6.0** (обов'язкова, використовується `$()`)

### Функція 1: Копіювання тексту пасту

**Обробник:** `$(document).on('click', '#copy-btn', ...)`

Алгоритм:

1. Читає текст з `#paste-textarea`
2. Якщо доступний `navigator.clipboard` + `isSecureContext` → `clipboard.writeText()` (сучасний API)
3. Інакше — fallback через `document.execCommand('copy')` (створює тимчасовий `textarea`, .select(), .copy())
4. Показує повідомлення `#copy-msg` (fade in → 2с → fade out)

**DOM-елементи:**
| Елемент | Використання |
|---|---|
| `#paste-textarea` | Джерело тексту для копіювання |
| `#copy-btn` | Кнопка копіювання |
| `#copy-msg` | Повідомлення «Скопійовано!» |

**Шаблон:** `templates/paste_view.php`

---

### Функція 2: Квест Adsterra (перегляд реклами)

**Обробник:** `$('#start-quest-btn').on('click', ...)`

Алгоритм:

1. Ховає кнопку `#start-quest-btn`, показує `#quest-timer-container`
2. Запускає зворотній відлік 10 секунд з прогрес-баром `#quest-progress-bar`
3. По завершенню — AJAX POST на `api/webhooks/verify_ad.php` з параметрами:
   - `paste_id` (з `data-paste-id`)
   - `ad_token` (з `data-ad-token`)
   - `csrf_token` (з `data-csrf-token`)
4. Якщо `data.done` — алерт + `location.reload()` (доступ відкрито)
5. Якщо НЕ done — оновлює `data-ad-token`, показує кнопку знову, лічильник скидається

**DOM-елементи:**
| Елемент | Використання |
|---|---|
| `#start-quest-btn` | Кнопка старту квесту (дата-атрибути: `paste-id`, `ad-token`, `csrf-token`) |
| `#quest-timer-container` | Контейнер таймера |
| `#quest-timer` | Число секунд |
| `#quest-progress-bar` | Прогрес-бар (CSS width) |
| `#ads-count` | Кількість зарахованих реклам |

**Шаблон:** `templates/paste_view.php`
**API:** `api/webhooks/verify_ad.php` (див. [api.md](api.md))

---

## 🔐 js/passkey.js — WebAuthn/FIDO2 клієнт

### Залежності

- **Жодних зовнішніх бібліотек** (чистий vanilla JS + Fetch API)
- Вимагає **HTTPS або localhost** (`window.isSecureContext`)
- Вимагає **WebAuthn API** (`window.PublicKeyCredential`)

### Архітектура

Скрипт реалізує повний цикл WebAuthn на стороні браузера:

- **Attestation Flow** (реєстрація) → `navigator.credentials.create()`
- **Assertion Flow** (вхід/підтвердження) → `navigator.credentials.get()`

Взаємодія з сервером через `api/passkey.php` (див. [api.md](api.md)).

### Утиліти конвертації Binary ↔ Base64URL

WebAuthn API працює з `ArrayBuffer`, а JSON-передача — з рядками. Проміжний формат — **Base64URL** (Base64 без `+`, `/`, `=`).

| Функція                     | Призначення                                                 |
| --------------------------- | ----------------------------------------------------------- |
| `buf2base64url(buffer)`     | ArrayBuffer → Base64URL рядок (`btoa()` + заміна символів)  |
| `base64url2buf(base64url)`  | Base64URL → ArrayBuffer (зворотна, з padding)               |
| `decodeClientDataJSON(buf)` | ArrayBuffer → JSON-об'єкт (TextDecoder + JSON.parse)        |
| `arrayBufferToObject(data)` | Універсальний хелпер: ArrayBuffer → JSON, або повертає як є |

### Утиліти повідомлень

| Функція                   | Цільовий DOM-елемент | Fallback  |
| ------------------------- | -------------------- | --------- |
| `showPasskeyError(msg)`   | `#passkey-error`     | `alert()` |
| `showPasskeySuccess(msg)` | `#passkey-success`   | `alert()` |

Елементи `#passkey-error` / `#passkey-success` — це `.alert-danger` / `.alert-success` блоки, визначені в `templates/login.php` та `templates/settings.php`.

### Основні функції

#### `registerPasskey(initialNickname)` — Реєстрація нового passkey

| Крок | Дія                                                                                                     |
| ---- | ------------------------------------------------------------------------------------------------------- |
| 1    | Перевірка `PublicKeyCredential` + `isSecureContext`                                                     |
| 2    | GET `api/passkey.php?action=register_start&nickname=…` — отримати `PublicKeyCredentialCreationOptions`  |
| 3    | Конвертація `challenge`, `user.id`, `excludeCredentials[].id` з Base64URL → ArrayBuffer                 |
| 4    | `navigator.credentials.create({ publicKey: options })` — діалог аутентифікатора                         |
| 5    | Серіалізація результату: `id`, `type`, `clientDataJSON`, `attestationObject`, `transports` → Base64URL  |
| 6    | POST `api/passkey.php?action=register_finish` з JSON `{ credential }` + CSRF-токен через `X-CSRF-Token` |
| 7    | Успіх → redirect через 1с                                                                               |

**Обробка помилок:** `NotAllowedError` (скасовано), `NotSupportedError` (аутентифікатор не підтримується), `SecurityError` (HTTPS проблеми).

**Виклик з шаблонів:**

- `templates/login.php`: `onclick="registerPasskey()"` (кнопка `#btn-passkey-register`)
- `templates/settings.php`: `onclick="registerPasskey('nickname')"` (передає поточний нікнейм)

---

#### `loginWithPasskey()` — Вхід через passkey

| Крок | Дія                                                                                                        |
| ---- | ---------------------------------------------------------------------------------------------------------- |
| 1    | Перевірка підтримки WebAuthn                                                                               |
| 2    | GET `api/passkey.php?action=login_start` — отримати `PublicKeyCredentialRequestOptions`                    |
| 3    | Конвертація `challenge`, `allowCredentials[].id` → ArrayBuffer                                             |
| 4    | `navigator.credentials.get({ publicKey: options })` — аутентифікатор підписує challenge                    |
| 5    | Серіалізація: `credentialId`, `clientDataJSON`, `authenticatorData`, `signature`, `userHandle` → Base64URL |
| 6    | POST `api/passkey.php?action=login_finish` з JSON `{ credential }` (без CSRF — challenge захищає)          |
| 7    | Успіх → redirect через 1с                                                                                  |

> **Примітка:** CSRF-токен не передається при вході, бо одноразовий challenge є nonce-ом і вже захищає від CSRF.

**Виклик з шаблонів:**

- `templates/login.php`: `onclick="loginWithPasskey()"` (кнопка `#btn-passkey-login`)

---

#### `deletePasskey(passkeyId)` — Видалення passkey

| Крок | Дія                                                                                 |
| ---- | ----------------------------------------------------------------------------------- |
| 1    | `confirm()` — підтвердження користувача                                             |
| 2    | POST `api/passkey.php?action=delete` (form-encoded) з `passkey_id` + `X-CSRF-Token` |
| 3    | Успіх → redirect на `settings.php`                                                  |

**Виклик з шаблонів:**

- `templates/settings.php`: `onclick="deletePasskey(id)"` (кнопки біля кожного passkey)

---

#### `confirmDeleteAccountPasskey()` — Підтвердження видалення акаунту через passkey

Двохетапний процес захисту від випадкового видалення:

| Крок | Дія                                                                                                         |
| ---- | ----------------------------------------------------------------------------------------------------------- |
| 1    | Подвійне `confirm()` попередження                                                                           |
| 2    | GET `api/passkey.php?action=login_start` — отримати challenge                                               |
| 3    | `navigator.credentials.get()` — passkey підписує challenge (підтвердження особи)                            |
| 4    | POST `api/passkey.php?action=confirm_delete_finish` — верифікація підпису                                   |
| 5    | Сервер підтвердив → **програмне створення форми** з `csrf_token` + `action=delete_account`, `form.submit()` |

> **Чому програмна форма?** Видаляння акаунту — це POST на `settings.php` (не API-ендпоінт). CSRF-токен обов'язковий, а JSON-запит до `settings.php` не пройде. Тому клієнт створює реальну форму і сабмітить її.

**Виклик з шаблонів:**

- `templates/settings.php`: `onclick="confirmDeleteAccountPasskey()"` (кнопка «Видалити акаунт»)

---

#### `checkWebAuthnSupport()` — Перевірка підтримки браузером

Автозапуск при завантаженні сторінки. Якщо `PublicKeyCredential` недоступний — усі кнопки з класом `.passkey-btn` вимикаються (`disabled`, `.disabled`, title-підказка).

Повертає `boolean`.

---

## 🎛️ js/theme-switch.js — Миттєвий перемикач тем

### Залежності

- **Жодних** (чистий vanilla JS, IIFE)

### Логіка

Скрипт загорнутий в IIFE `(function() { ... })()` — не забруднює глобальну область видимості.

**1. Миттєвий прев'ю теми:**

- Слухає `change` на усіх `input[name="theme"]` (radio-кнопки)
- При зміні → `document.documentElement.setAttribute('data-theme', value)`
- CSS-змінні перемикаються миттєво (завдяки `transition` в `style.css`)

**2. Клік на картку теми:**

- Слухає клік на `.theme-card`
- Знаходить `input[name="theme"]` всередині картки
- Встановлює `radio.checked = true`, диспатчить `change`
- Оновлює `.theme-card-active` клас на клікнутій картці

> **Важливо:** Цей скрипт лише змінює `data-theme` на `<html>` для прев'ю. Фактичне збереження теми відбувається через POST-форму в `SettingsController.php` при натисканні «Зберегти».

**Шаблон:** `templates/settings.php` (секція вибору теми)

---

## 🖼️ img/ — Зображення

### logo.png

| Параметр     | Значення                        |
| ------------ | ------------------------------- |
| Формат       | PNG                             |
| Розмір       | 686 × 686 px                    |
| Глибина      | 8-bit/color RGBA (з прозорістю) |
| Розмір файлу | ~425 KB                         |
| Використання | favicon, navbar brand, og:image |

**Місця використання:**

1. `<link rel="icon">` — favicon (`header.php`)
2. `<img>` в навбарі — 24px висота, inline-block (`header.php`)
3. `<meta property="og:image">` — Open Graph для соцмереж (`header.php`)

---

## 🔗 Зв'язки з іншими компонентами

| Цей ресурс        | Взаємодіє з                       | Природа зв'язку                                   |
| ----------------- | --------------------------------- | ------------------------------------------------- |
| `style.css`       | `templates/header.php`            | Підключення через `<link>`                        |
| `style.css`       | `includes/SettingsController.php` | PHP встановлює `data-theme` з БД/сесії            |
| `app.js`          | `api/webhooks/verify_ad.php`      | AJAX-запит підтвердження реклами                  |
| `app.js`          | `templates/paste_view.php`        | DOM-елементи для копіювання та квесту             |
| `passkey.js`      | `api/passkey.php`                 | WebAuthn протокол (4 ендпоінти)                   |
| `passkey.js`      | `templates/login.php`             | Кнопки реєстрації/входу через passkey             |
| `passkey.js`      | `templates/settings.php`          | Управління passkey, видалення акаунту             |
| `passkey.js`      | `includes/webauthn.php`           | Серверна частина WebAuthn (генерація/верифікація) |
| `theme-switch.js` | `templates/settings.php`          | Картки тем + radio-кнопки                         |
| `theme-switch.js` | `style.css`                       | Зміна `data-theme` → активація CSS-змінних        |
| `logo.png`        | `templates/header.php`            | favicon + navbar + og:image                       |
| jQuery 3.6.0      | `app.js`                          | Обов'язкова залежність                            |
| Bootstrap 3.4.1   | `style.css`                       | Базові класи, які перевизначаються                |

---

## ⚠️ Зауваження та обмеження

1. **`!important` скрізь** — через необхідність перевизначити Bootstrap 3. Якщо проект перейде на Bootstrap 5 або видалить Bootstrap, `!important` можна буде прибрати.
2. **Хардкод кольорів** — `.btn-success` зберігає `border-color: #5cb85c` (зелений Bootstrap), що може виглядати негармонійно в деяких темах.
3. **CDN-залежність** — jQuery та Bootstrap JS підключаються з CDN. При офлайн-роботі UI буде без функціоналу копіювання та квесту.
4. **logo.png розмір** — 425 KB для логотипу — досить великий. Рекомендується оптимізація (TinyPNG або SVG-версія).
5. **passkey.js + HTTP** — WebAuthn НЕ працює на HTTP (окрім localhost). На продакшені обов'язковий HTTPS.

---

## Перехресні посилання

| Пов'язаний документ                | Що деталізує                                                 |
| ---------------------------------- | ------------------------------------------------------------ |
| [templates.md](templates.md)       | Шаблони що підключають CSS/JS та використовують DOM-елементи |
| [api.md](api.md)                   | API-endpoint'и що викликаються з JS-коду                     |
| [includes.md](includes.md)         | Серверні утиліти (WebAuthn, CSRF) що взаємодіють з клієнтом  |
| [architecture.md](architecture.md) | Загальний огляд архітектури та екосистеми                    |
