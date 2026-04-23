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
│   └── webauthn.php    # WebAuthn утиліти
├── templates/          # HTML шаблони
├── uploads/            # Завантажені файли
├── .env.example        # Шаблон конфігурації
└── index.php           # Головна сторінка
```

## 📄 Ліцензія

Цей проект створений виключно в навчальних цілях.
