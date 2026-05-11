# 📚 Документація PayPaste

> Повна структурована документація проекту PayPaste — pastebin-сервісу з монетизацією доступу та ретро-стилізацією 2000-х.

**Для кого:** для розробників, які вперше знайомляться з проектом, а також для викладача/рецензента курсового проекту. Почніть з `architecture.md` — він містить вступ, глосарій та quick start.

### Термінологія

У документації використовуються як українські, так і англійські терміни — це відображає реальний код проекту:

| Українською | Англійською | Пояснення                             |
| ----------- | ----------- | ------------------------------------- |
| Паста       | Paste       | Збережений текстовий/кодовий фрагмент |
| Кредити     | Credits     | Внутрішня валюта системи              |
| Квест       | Ad Quest    | Перегляд реклами замість оплати       |
| Замовлення  | Order       | Запит на поповнення балансу           |
| Транзакція  | Transaction | Запис операції з кредитами            |

---

## Структура документації

| Документ                                 | Зміст                                                                                                                                                                                                    |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **[architecture.md](architecture.md)**   | 🔝 Вхідна точка. Загальний огляд, архітектура MVC, потік даних, екосистема, безпека, економіка, розгортання                                                                                              |
| [config.md](config.md)                   | Конфігурація, усі константи з описом, клас DB (Singleton PDO), повна схема БД (10 таблиць), ER-діаграма                                                                                                  |
| [includes.md](includes.md)               | Моделі (User, Paste, Order, Transaction, Passkey), сервіси (AuthService, CreditService, PasteService, AdQuestService), контролери, утиліти (CSRF, JWT, Moderation, RateLimiter, Queue, WebAuthn, Mailer) |
| [api.md](api.md)                         | REST API пасти (GET/POST/DELETE), JWT-автентифікація, OAuth (GitHub+Telegram), WebAuthn/Passkey, платіжні webhooks (Donatello, Telegram Stars), рекламний квест (verify_ad)                              |
| [templates.md](templates.md)             | Усі шаблони (header, footer, home, create, paste_view, login, settings, verify, credits), email-шаблони, entry points, змінні, форми, потік даних                                                        |
| [admin-cron-data.md](admin-cron-data.md) | Адмін-панель (CRUD паст/користувачів, транзакції, черга), Worker (cron/worker.php), cleanup, файлове сховище (data/uploads, .htaccess)                                                                   |
| [assets.md](assets.md)                   | CSS (6 тем, Bootstrap 3 override, responsive), JS (app.js, passkey.js, theme-switch.js), статика                                                                                                         |

---

## Як читати

1. Почніть з **architecture.md** — загальний контекст проекту
2. За потреби заглибтесь у конкретний модуль за таблицею вище
3. Кожен документ містить перехресні посилання на пов'язані розділи

---

## Зв'язність документів

```
architecture.md (огляд)
  ├──→ config.md (константи, БД-схема)
  ├──→ includes.md (моделі, сервіси, контролери, утиліти)
  │      └──→ config.md (таблиці, константи)
  ├──→ api.md (REST API, webhooks, OAuth)
  │      ├──→ includes.md (сервіси, моделі)
  │      └──→ config.md (API-ключі, токени)
  ├──→ templates.md (UI, форми, потік)
  │      ├──→ includes.md (контролери, моделі)
  │      └──→ api.md (AJAX-запити з шаблонів)
  ├──→ admin-cron-data.md (адмінка, worker, data/)
  │      ├──→ includes.md (сервіси, Queue, Moderation)
  │      └──→ config.md (таблиця jobs, cron-налаштування)
  └──→ assets.md (CSS, JS)
         └──→ templates.md (DOM-елементи що використовуються)
```

---

_Написано: 2026-05-11_
