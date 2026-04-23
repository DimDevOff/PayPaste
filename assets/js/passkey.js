/*
 * Реалізує повний цикл роботи з Passkey (FIDO2/WebAuthn) на стороні браузера:
 *   - Реєстрація нового passkey (registerPasskey)
 *   - Вхід через passkey (loginWithPasskey)
 *   - Видалення passkey (deletePasskey)
 *   - Підтвердження видалення акаунту через passkey (confirmDeleteAccountPasskey)
 *   - Перевірка підтримки WebAuthn браузером (checkWebAuthnSupport)
 *
 * Взаємодіє з серверним API: api/passkey.php
 * Використовує стандарт Base64URL для кодування бінарних даних між JS і PHP.
 */

//
// УТИЛІТИ: КОНВЕРТАЦІЯ BINARY ↔ BASE64URL
//

/**
 * Конвертує ArrayBuffer (або Uint8Array) у рядок Base64URL.
 *
 * Base64URL — це Base64 без символів '+', '/' та '=',
 * замінених на '-', '_' та без padding.
 * Використовується у WebAuthn для передачі бінарних даних через JSON.
 *
 * @param {ArrayBuffer} buffer - Бінарні дані (наприклад, credential.id, challenge)
 * @returns {string} Рядок у форматі Base64URL
 */
function buf2base64url(buffer) {
    // Обгортаємо буфер у Uint8Array для побайтового доступу
    const bytes = new Uint8Array(buffer);
    let binary = '';
    // Конвертуємо кожен байт у символ ASCII
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    // btoa() → стандартний Base64, далі замінюємо символи на URL-safe варіант
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/**
 * Конвертує рядок Base64URL назад у ArrayBuffer.
 *
 * Зворотна до buf2base64url. Використовується для відновлення бінарних
 * даних із відповідей сервера (challenge, credential id тощо).
 *
 * @param {string} base64url - Рядок у форматі Base64URL
 * @returns {ArrayBuffer} Бінарні дані
 */
function base64url2buf(base64url) {
    // Повертаємо URL-небезпечні символи назад у стандартний Base64
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    // Base64 має бути кратним 4 — додаємо padding '=' якщо потрібно
    const pad = base64.length % 4;
    const padded = pad ? base64 + '='.repeat(4 - pad) : base64;
    // Декодуємо Base64 у бінарний рядок, потім у Uint8Array
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    // Повертаємо як ArrayBuffer (потрібно для WebAuthn API)
    return bytes.buffer;
}

/**
 * Декодує clientDataJSON з ArrayBuffer у JavaScript-об'єкт.
 *
 * clientDataJSON — це JSON-структура, підписана аутентифікатором,
 * що містить challenge, origin, тип операції тощо.
 *
 * @param {ArrayBuffer} clientDataJSON - Бінарні дані з відповіді аутентифікатора
 * @returns {Object} Розпарсений об'єкт clientData
 */
function decodeClientDataJSON(clientDataJSON) {
    // TextDecoder перетворює бінарний буфер у рядок UTF-8, потім JSON.parse у об'єкт
    return JSON.parse(new TextDecoder().decode(clientDataJSON));
}

/**
 * Універсальний хелпер: якщо data є ArrayBuffer — декодує як JSON,
 * інакше повертає без змін.
 *
 * @param {ArrayBuffer|*} data - Вхідні дані
 * @returns {Object|*} Декодований об'єкт або оригінальні дані
 */
function arrayBufferToObject(data) {
    if (data instanceof ArrayBuffer) {
        const decoder = new TextDecoder();
        const jsonStr = decoder.decode(data);
        return JSON.parse(jsonStr);
    }
    return data;
}

//
// УТИЛІТИ: ВІДОБРАЖЕННЯ ПОВІДОМЛЕНЬ КОРИСТУВАЧУ
//

/**
 * Відображає повідомлення про помилку у відведеному елементі сторінки.
 *
 * Шукає елемент з id="passkey-error". Якщо знайдено — показує
 * текст у ньому. Якщо елемент відсутній — fallback через alert().
 *
 * @param {string} message - Текст повідомлення про помилку
 */
function showPasskeyError(message) {
    const msgEl = document.getElementById('passkey-error');
    if (msgEl) {
        msgEl.textContent = message;
        msgEl.style.display = 'block';
    } else {
        // Fallback якщо відповідний DOM-елемент не знайдено
        alert('Помилка: ' + message);
    }
}

/**
 * Відображає повідомлення про успіх у відведеному елементі сторінки.
 *
 * Шукає елемент з id="passkey-success". Якщо знайдено — показує
 * текст у ньому. Якщо елемент відсутній — fallback через alert().
 *
 * @param {string} message - Текст повідомлення про успіх
 */
function showPasskeySuccess(message) {
    const msgEl = document.getElementById('passkey-success');
    if (msgEl) {
        msgEl.textContent = message;
        msgEl.style.display = 'block';
    } else {
        // Fallback якщо відповідний DOM-елемент не знайдено
        alert('Успіх: ' + message);
    }
}

//
// ОСНОВНІ ФУНКЦІЇ: РЕЄСТРАЦІЯ PASSKEY
//

/**
 * Реєструє новий Passkey для користувача (WebAuthn Attestation Flow).
 *
 * Алгоритм:
 *   1. Перевіряє підтримку WebAuthn та HTTPS
 *   2. Запитує у сервера параметри реєстрації (challenge, user.id тощо)
 *   3. Конвертує Base64URL → ArrayBuffer для WebAuthn API
 *   4. Викликає navigator.credentials.create() — браузер запитує аутентифікатор
 *   5. Серіалізує результат (credentialData) назад у Base64URL
 *   6. Відправляє дані на сервер для верифікації та збереження
 *   7. При успіху — перенаправляє користувача
 *
 * @param {string} initialNickname - Нікнейм нового користувача (або порожній)
 */
async function registerPasskey(initialNickname) {
    // Перевірка: чи підтримує браузер WebAuthn API
    if (!window.PublicKeyCredential) {
        showPasskeyError('Ваш браузер не підтримує WebAuthn. Використовуйте Chrome, Edge, Firefox або Safari.');
        return;
    }

    // Перевірка: WebAuthn вимагає HTTPS або localhost (Secure Context)
    if (!window.isSecureContext) {
        showPasskeyError('WebAuthn працює тільки на HTTPS або localhost');
        return;
    }

    try {
        // Нікнейм за замовчуванням якщо не переданий
        const nickname = initialNickname || 'PasskeyUser';

        // КРОК 1: Запит на сервер — отримуємо PublicKeyCredentialCreationOptions
        // Сервер генерує унікальний challenge та параметри для аутентифікатора
        const startResp = await fetch('api/passkey.php?action=register_start&nickname=' + encodeURIComponent(nickname));
        const startData = await startResp.json();

        if (!startData.success) {
            showPasskeyError(startData.error || 'Помилка при початку реєстрації');
            return;
        }

        const options = startData.options;

        // КРОК 2: Конвертуємо challenge з Base64URL у ArrayBuffer
        // WebAuthn API приймає виключно бінарні дані, не рядки
        options.challenge = base64url2buf(options.challenge);

        // Конвертуємо user.id (унікальний ідентифікатор користувача для аутентифікатора)
        options.user.id = base64url2buf(options.user.id);

        // Конвертуємо excludeCredentials — список вже зареєстрованих ключів,
        // щоб аутентифікатор не створював дублікати
        if (options.excludeCredentials) {
            options.excludeCredentials = options.excludeCredentials.map(cred => ({
                ...cred,
                id: base64url2buf(cred.id)
            }));
        }

        // КРОК 3: Виклик WebAuthn API — браузер показує діалог вибору аутентифікатора
        // (TouchID, Windows Hello, YubiKey тощо)
        const credential = await navigator.credentials.create({
            publicKey: options
        });

        // КРОК 4: Серіалізація відповіді аутентифікатора для відправки на сервер
        // Конвертуємо ArrayBuffer назад у Base64URL для JSON
        const credentialData = {
            id: credential.id,                                          // Унікальний ID ключа (Base64URL, вже рядок)
            type: credential.type,                                       // Тип: "public-key"
            clientDataJSON: buf2base64url(credential.response.clientDataJSON),     // JSON з challenge, origin тощо
            attestationObject: buf2base64url(credential.response.attestationObject), // Підписані дані аутентифікатора
            // Список транспортів (usb, nfc, ble, internal) — для інформації
            transports: credential.response.getTransports ? credential.response.getTransports() : []
        };

        // Зчитуємо CSRF-токен зі схованого поля форми для захисту від CSRF-атак
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        // КРОК 5: Відправляємо дані на сервер для верифікації attestation
        const finishResp = await fetch('api/passkey.php?action=register_finish', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken  // CSRF-захист через заголовок
            },
            body: JSON.stringify({ credential: credentialData })
        });

        const finishData = await finishResp.json();

        // КРОК 6: Обробка результату реєстрації
        if (finishData.success) {
            showPasskeySuccess('Passkey успішно прив\'язано!');
            // Затримка 1с щоб користувач побачив повідомлення про успіх
            setTimeout(() => {
                window.location.href = finishData.redirect || 'index.php';
            }, 1000);
        } else {
            showPasskeyError(finishData.error || 'Помилка при завершенні реєстрації');
        }

    } catch (err) {
        // Обробка специфічних WebAuthn помилок
        console.error('Passkey register error:', err);
        if (err.name === 'NotAllowedError') {
            // Користувач відмінив діалог або час очікування вийшов
            showPasskeyError('Реєстрацію скасовано користувачем');
        } else if (err.name === 'NotSupportedError') {
            // Аутентифікатор не підтримує потрібний алгоритм (наприклад, ES256)
            showPasskeyError('Цей тип аутентифікатора не підтримується');
        } else if (err.name === 'SecurityError') {
            // Невідповідний origin або rpId
            showPasskeyError('Помилка безпеки: переконайтесь що використовуєте HTTPS');
        } else {
            showPasskeyError('Помилка: ' + (err.message || err.name));
        }
    }
}

//
// ОСНОВНІ ФУНКЦІЇ: ВХІД ЧЕРЕЗ PASSKEY
//

/**
 * Виконує вхід користувача через існуючий Passkey (WebAuthn Assertion Flow).
 *
 * Алгоритм:
 *   1. Перевіряє підтримку WebAuthn та HTTPS
 *   2. Запитує у сервера challenge та список дозволених ключів
 *   3. Конвертує Base64URL → ArrayBuffer
 *   4. Викликає navigator.credentials.get() — аутентифікатор підписує challenge
 *   5. Серіалізує підпис та дані назад у Base64URL
 *   6. Відправляє на сервер для верифікації підпису
 *   7. При успіху — перенаправляє користувача
 */
async function loginWithPasskey() {
    // Перевірка підтримки WebAuthn
    if (!window.PublicKeyCredential) {
        showPasskeyError('Ваш браузер не підтримує WebAuthn. Використовуйте Chrome, Edge, Firefox або Safari.');
        return;
    }

    // Перевірка Secure Context (обов'язково для WebAuthn)
    if (!window.isSecureContext) {
        showPasskeyError('WebAuthn працює тільки на HTTPS або localhost');
        return;
    }

    try {
        // КРОК 1: Запит на сервер — отримуємо PublicKeyCredentialRequestOptions
        // Сервер генерує унікальний challenge для підпису
        const startResp = await fetch('api/passkey.php?action=login_start');
        const startData = await startResp.json();

        if (!startData.success) {
            showPasskeyError(startData.error || 'Помилка при початку входу');
            return;
        }

        const options = startData.options;

        // КРОК 2: Конвертуємо challenge у ArrayBuffer
        options.challenge = base64url2buf(options.challenge);
        // rpId залишається як рядок — це домен сайту
        options.rpId = options.rpId;

        // Конвертуємо allowCredentials — список ключів, якими дозволено підписувати
        // Якщо список порожній — браузер запропонує будь-який доступний ключ (discoverable)
        if (options.allowCredentials && options.allowCredentials.length > 0) {
            options.allowCredentials = options.allowCredentials.map(cred => ({
                ...cred,
                id: base64url2buf(cred.id)
            }));
        }

        // КРОК 3: Виклик WebAuthn API — аутентифікатор підписує challenge
        const credential = await navigator.credentials.get({
            publicKey: options
        });

        // КРОК 4: Отримуємо дані з відповіді аутентифікатора
        const clientDataJSON = credential.response.clientDataJSON;     // JSON з challenge, origin тощо
        // const clientData = arrayBufferToObject(clientDataJSON); // Не використовується на клієнті

        const authData = new Uint8Array(credential.response.authenticatorData); // Дані аутентифікатора (лічильник, флаги)
        const sig = credential.response.signature;                               // Криптографічний підпис

        // КРОК 5: Серіалізація у Base64URL для відправки через JSON
        const credentialData = {
            credentialId: credential.id,                                        // ID ключа що підписав
            clientDataJSON: buf2base64url(clientDataJSON),                      // Підписаний JSON клієнта
            authenticatorData: buf2base64url(authData),                         // Дані аутентифікатора
            signature: buf2base64url(sig),                                      // Підпис для верифікації
            // userHandle — опціональний userId від аутентифікатора (якщо discoverable credential)
            userHandle: credential.response.userHandle ? buf2base64url(credential.response.userHandle) : null
        };

        // КРОК 6: Відправка на сервер для верифікації підпису публічним ключем
        const finishResp = await fetch('api/passkey.php?action=login_finish', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
                // CSRF-токен не потрібен: challenge є одноразовим nonce-ом і вже захищає від CSRF
            },
            body: JSON.stringify({ credential: credentialData })
        });

        const finishData = await finishResp.json();

        // КРОК 7: Обробка результату входу
        if (finishData.success) {
            showPasskeySuccess('Вхід успішний!');
            setTimeout(() => {
                window.location.href = finishData.redirect || 'index.php';
            }, 1000);
        } else {
            showPasskeyError(finishData.error || 'Помилка при вході');
        }

    } catch (err) {
        // Обробка специфічних WebAuthn помилок при вході
        console.error('Помилка входу Passkey:', err);
        if (err.name === 'NotAllowedError') {
            // Користувач закрив діалог або час очікування вийшов
            showPasskeyError('Вхід скасовано користувачем');
        } else if (err.name === 'NotSupportedError') {
            showPasskeyError('Цей тип аутентифікатора не підтримується');
        } else if (err.name === 'SecurityError') {
            showPasskeyError('Помилка безпеки: переконайтесь що використовуєте HTTPS');
        } else {
            showPasskeyError('Помилка: ' + (err.message || err.name));
        }
    }
}

//
// ОСНОВНІ ФУНКЦІЇ: ВИДАЛЕННЯ PASSKEY
//

/**
 * Видаляє конкретний Passkey зі збережених ключів користувача.
 *
 * Перед видаленням запитує підтвердження. Після успішного
 * видалення перенаправляє назад на сторінку налаштувань.
 *
 * @param {string|number} passkeyId - ID passkey у базі даних
 */
async function deletePasskey(passkeyId) {
    // Запит підтвердження перед незворотною дією
    if (!confirm('Точно видалити цей passkey? Ця дія незворотна!')) {
        return;
    }

    try {
        // Зчитуємо CSRF-токен для захисту POST-запиту
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        // Відправляємо запит на видалення (form-encoded, не JSON)
        const resp = await fetch('api/passkey.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: 'passkey_id=' + encodeURIComponent(passkeyId)
        });

        const data = await resp.json();

        if (data.success) {
            showPasskeySuccess('Passkey видалено!');
            // Перенаправляємо на settings.php щоб оновити список ключів
            setTimeout(() => {
                window.location.href = data.redirect || 'settings.php';
            }, 1000);
        } else {
            showPasskeyError(data.error || 'Помилка при видаленні');
        }

    } catch (err) {
        console.error('Помилка видалення Passkey:', err);
        showPasskeyError('Помилка: ' + (err.message || err.name));
    }
}

//
// ОСНОВНІ ФУНКЦІЇ: ВИДАЛЕННЯ АКАУНТУ З ПІДТВЕРДЖЕННЯМ PASSKEY
//

/**
 * Видаляє акаунт користувача, але спочатку вимагає підтвердження через Passkey.
 *
 * Двохетапний процес:
 *   1. Показує попередження та запитує підтвердження у діалозі
 *   2. Проводить WebAuthn Assertion (вхід через passkey) як підтвердження особи
 *   3. Якщо верифікація успішна — сервер повертає дозвіл
 *   4. Клієнт програмно відправляє POST-форму на видалення акаунту
 *      (з CSRF-токеном, щоб обійти захист)
 *
 * Такий підхід захищає від випадкового або несанкціонованого видалення акаунту.
 */
async function confirmDeleteAccountPasskey() {
    // Подвійне попередження — дія незворотна
    if (!confirm('ВИ ВПЕВНЕНІ? Ця дія НЕЗВОРОТНА! Ваш акаунт буде видалено після підтвердження Passkey.')) {
        return;
    }

    try {
        // КРОК 1: Отримуємо challenge від сервера (використовуємо той самий login_start)
        const startResp = await fetch('api/passkey.php?action=login_start');
        const startData = await startResp.json();

        if (!startData.success) {
            showPasskeyError(startData.error || 'Помилка при початку перевірки');
            return;
        }

        const options = startData.options;

        // Конвертуємо challenge у ArrayBuffer для WebAuthn API
        options.challenge = base64url2buf(options.challenge);

        // Конвертуємо ID дозволених ключів
        if (options.allowCredentials) {
            options.allowCredentials = options.allowCredentials.map(cred => ({
                ...cred,
                id: base64url2buf(cred.id)
            }));
        }

        // КРОК 2: Аутентифікатор підписує challenge — підтверджує особу користувача
        const credential = await navigator.credentials.get({
            publicKey: options
        });

        // КРОК 3: Серіалізуємо відповідь аутентифікатора
        const clientDataJSON = credential.response.clientDataJSON;
        const authData = new Uint8Array(credential.response.authenticatorData);
        const sig = credential.response.signature;

        const credentialData = {
            credentialId: credential.id,
            clientDataJSON: buf2base64url(clientDataJSON),
            authenticatorData: buf2base64url(authData),
            signature: buf2base64url(sig),
            userHandle: credential.response.userHandle ? buf2base64url(credential.response.userHandle) : null
        };

        // КРОК 4: Верифікація підпису на сервері через окремий endpoint
        // confirm_delete_finish — перевіряє підпис та встановлює сесійний прапор дозволу
        const finishResp = await fetch('api/passkey.php?action=confirm_delete_finish', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ credential: credentialData })
        });

        const finishData = await finishResp.json();

        if (finishData.success) {
            // КРОК 5: Сервер підтвердив особу — тепер програмно відправляємо
            // POST-форму на видалення акаунту (потрібна для передачі CSRF-токена)
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'settings.php';

            // Поле CSRF-токена — захист від міжсайтових підробок запитів
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = document.querySelector('input[name="csrf_token"]')?.value || '';

            // Поле action — вказує серверу що потрібно видалити акаунт
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_account';

            // Збираємо форму та відправляємо
            form.appendChild(csrfInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();

        } else {
            showPasskeyError(finishData.error || 'Помилка при підтвердженні Passkey');
        }

    } catch (err) {
        console.error('Помилка підтвердження Passkey:', err);
        if (err.name === 'NotAllowedError') {
            // Користувач відмінив діалог — акаунт залишається
            showPasskeyError('Дію скасовано');
        } else {
            showPasskeyError('Помилка: ' + (err.message || err.name));
        }
    }
}

//
// ІНІЦІАЛІЗАЦІЯ: ПЕРЕВІРКА ПІДТРИМКИ WEBAUTHN
//

/**
 * Перевіряє підтримку WebAuthn браузером і блокує кнопки якщо не підтримується.
 *
 * Знаходить усі елементи з класом .passkey-btn і:
 *   - Якщо WebAuthn НЕ підтримується: вимикає кнопки (disabled + клас 'disabled')
 *     та встановлює title-підказку з поясненням
 *   - Якщо підтримується: нічого не робить, кнопки залишаються активними
 *
 * @returns {boolean} true якщо WebAuthn підтримується, false якщо ні
 */
function checkWebAuthnSupport() {
    if (!window.PublicKeyCredential) {
        // WebAuthn не підтримується — блокуємо всі passkey-кнопки на сторінці
        const passkeyBtns = document.querySelectorAll('.passkey-btn');
        passkeyBtns.forEach(btn => {
            btn.disabled = true;
            btn.title = 'WebAuthn не підтримується цим браузером';
            btn.classList.add('disabled');
        });
        return false;
    }
    return true;
}

//
// ЗАПУСК: Виконуємо перевірку підтримки після завантаження DOM
//

if (document.readyState === 'loading') {
    // DOM ще завантажується — чекаємо на подію DOMContentLoaded
    document.addEventListener('DOMContentLoaded', checkWebAuthnSupport);
} else {
    // DOM вже готовий (скрипт підключено з defer або в кінці body) — запускаємо одразу
    checkWebAuthnSupport();
}