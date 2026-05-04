# 🏴‍☠️ PayPaste

> Індивідуальний проект з предмету "Вебтехнології" — сервіс для збереження фрагментів коду та тексту з ретро-стилістикою піратських сайтів 2000-х років.

## 🛠 Технічний стек

- **Backend:** PHP 8+ (процедурний + базове ООП)
- **Database:** MySQL 8+ (PDO)
- **Frontend:** HTML5, CSS3, JavaScript (jQuery + Fetch API)
- **UI Framework:** Bootstrap 3 (вінтажна стилістика)
- **Автентифікація:** Email/пароль, GitHub OAuth, Telegram OAuth, WebAuthn/FIDO2 Passkey

## 📋 Основні можливості

- ✅ Створення та перегляд паст (публічні, приватні, платні)
- ✅ Система кредитів з монетизацією (Donatello, Telegram Stars)
- ✅ OAuth вхід через GitHub та Telegram
- ✅ WebAuthn/FIDO2 Passkey автентифікація (YubiKey та ін.)
- ✅ Завантаження файлів (до 5 МБ)
- ✅ Час життя паст (TTL: від 10 хвилин до 7 днів)
- ✅ Адмін-панель (статистика, управління користувачами та контентом)
- ✅ CSRF-захист усіх форм
- ✅ Remember Me cookie (14 днів, HMAC-SHA256)
- ✅ AI-модерація та фонове перефразування токсичного контенту (Ollama Cloud)

## 🚀 Встановлення

### Вимоги
- PHP 8.0+
- MySQL 8.0+
- Apache з mod_rewrite
- Розширення PHP: `pdo_mysql`, `openssl`, `curl`, `mbstring`

### Кроки

1. **Клонувати репозиторій:**
   ```bash
   git clone https://github.com/YOUR_USERNAME/paypaste.git
   cd paypaste
   ```

2. **Налаштувати базу даних:**
   ```bash
   mysql -u root -p < config/paypaste.sql
   ```

3. **Створити конфігурацію:**
   ```bash
   cp .env.example .env
   # Відредагувати .env та вказати свої дані
   ```

4. **Налаштувати веб-сервер:**
   - Направити DocumentRoot на кореневу директорію проекту
   - Переконатися, що `mod_rewrite` увімкнено

## 📁 Структура проекту

```
├── admin/              # Адмін-панель
├── api/
│   ├── oauth.php       # GitHub та Telegram OAuth
│   ├── passkey.php     # WebAuthn API
│   ├── check_order_status.php
│   └── webhooks/       # Donatello та Telegram Stars
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── js/passkey.js
├── config/
│   ├── db.php          # PDO Singleton
│   ├── env.php         # .env парсер
│   └── paypaste.sql    # SQL схема
├── includes/
│   ├── controllers/    # AuthController, PasteController, SettingsController
│   ├── models/         # User, Paste, Order, Transaction, Passkey
│   ├── csrf.php        # CSRF + Remember Me
│   ├── webauthn.php    # WebAuthn утиліти
│   └── Moderation.php  # AI модерація (OpenAI/Ollama)
├── cron/
│   ├── cleanup.php     # Очищення TTL
│   └── ai_worker.php   # Фоновий воркер ШІ
├── templates/          # HTML шаблони
├── uploads/            # Завантажені файли
├── .env.example        # Шаблон конфігурації
└── index.php           # Головна сторінка
```

## ⏰ Автоматизація очищення (TTL)

Для того, щоб пасти з терміном дії, що минув, автоматично видалялися з бази даних та диска, необхідно налаштувати регулярний запуск скрипта `cron/cleanup.php`.

### Налаштування на Windows (PowerShell)

Запустіть PowerShell від імені Адміністратора та виконайте наступну команду, щоб створити завдання у Планувальнику завдань Windows (запуск щогодини):

```powershell
$action = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" -Argument "C:\xampp\htdocs\test.local\proj\cron\cleanup.php --force"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours 1)
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "PayPaste_Cleanup" -Description "Очищення старих паст щогодини"
```

### Налаштування на Linux (Cron)

Додайте наступний рядок до вашого `crontab -e`:

```bash
0 * * * * php /шлях/до/проекту/cron/cleanup.php --force > /dev/null 2>&1
```

```bash
C:\xampp\php\php.exe cron/cleanup.php --force
```

## 🧠 Фонова обробка ШІ (AI Worker)

Для роботи асинхронного перефразування тексту (коли паста не проходить модерацію) необхідно запустити фоновий процес, який буде обробляти чергу запитів до ШІ.

### Налаштування на Linux (Ubuntu/Debian)

Додайте наступний рядок до вашого `crontab -e` для перевірки черги кожну хвилину:

```bash
* * * * * /usr/bin/php /шлях/до/проекту/cron/ai_worker.php >> /шлях/до/проекту/data/ai_worker.log 2>&1
```

### Перевірка роботи черги (Manual)

```bash
php cron/ai_worker.php
```

## 📄 Ліцензія

Цей проект створений виключно в навчальних цілях.
