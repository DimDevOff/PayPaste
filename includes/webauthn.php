<?php
/**
 * Цей файл містить набір утиліт для роботи з протоколом WebAuthn (FIDO2).
 * Він відповідає за кодування/декодування даних, перевірку атестації (реєстрація)
 * та перевірку асерції (вхід) за допомогою Passkeys.
 * 
 * Реалізовано без зовнішніх залежностей (чистий PHP + OpenSSL).
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Кодує дані у формат Base64URL (без символів +, / та =)
 * @param string $data
 * @return string
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Декодує дані з формату Base64URL
 * @param string $data
 * @return string
 */
function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Генерує випадковий "челендж" для безпеки запитів WebAuthn
 * @param int $length
 * @return string
 */
function generateChallenge($length = 32) {
    return base64url_encode(random_bytes($length));
}

/**
 * Отримує налаштування WebAuthn з оточення (.env) або дефолтні значення
 * @return array
 */
function getWebAuthnConfig() {
    $app_url = APP_URL ?: 'https://YOUR_DOMAIN';
    $rp_id = WEBAUTHN_RP_ID ?: parse_url($app_url, PHP_URL_HOST);

    return [
        'rp_id' => $rp_id,
        'rp_name' => 'PayPaste',
        'origin' => WEBAUTHN_ORIGIN ?: $app_url
    ];
}

/**
 * Проста перевірка CSRF токена для API запитів
 * @param string $token
 * @return bool
 */
function verify_csrf_api($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

/**
 * Самописний декодер CBOR (Concise Binary Object Representation)
 * Проект не використовує Composer, реалізуємо базовий парсинг бінарних даних.
 * 
 * @param string $data Бінарні дані
 * @param int $offset Поточне зміщення
 * @return mixed Декодовані дані
 */
function cbor_decode($data, &$offset = 0) {
    if ($offset >= strlen($data)) {
        return null;
    }

    $byte = ord($data[$offset]);
    $major = ($byte >> 5) & 0x07;
    $minor = $byte & 0x1F;
    $offset++;

    $value = null;

    if ($minor < 24) {
        $value = $minor;
    } elseif ($minor === 24) {
        if ($offset >= strlen($data)) return null;
        $value = ord($data[$offset]);
        $offset++;
    } elseif ($minor === 25) {
        if ($offset + 2 > strlen($data)) return null;
        $value = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
    } elseif ($minor === 26) {
        if ($offset + 4 > strlen($data)) return null;
        $value = unpack('N', substr($data, $offset, 4))[1];
        $offset += 4;
    } elseif ($minor === 27) {
        if ($offset + 8 > strlen($data)) return null;
        $hi = unpack('N', substr($data, $offset, 4))[1];
        $lo = unpack('N', substr($data, $offset + 4, 4))[1];
        $value = $hi * 4294967296 + $lo;
        $offset += 8;
    } elseif ($minor === 31) {
        return null;
    }

    if ($major === 0) { // unsigned integer
        return $value;
    } elseif ($major === 1) { // negative integer
        return -1 - $value;
    } elseif ($major === 2) { // byte string
        if ($offset + $value > strlen($data)) return null;
        $bytes = substr($data, $offset, $value);
        $offset += $value;
        return $bytes;
    } elseif ($major === 3) { // text string
        if ($offset + $value > strlen($data)) return null;
        $str = substr($data, $offset, $value);
        $offset += $value;
        return $str;
    } elseif ($major === 4) { // array
        $arr = [];
        for ($i = 0; $i < $value; $i++) {
            $arr[] = cbor_decode($data, $offset);
        }
        return $arr;
    } elseif ($major === 5) { // map
        $map = [];
        for ($i = 0; $i < $value; $i++) {
            $key = cbor_decode($data, $offset);
            $val = cbor_decode($data, $offset);
            if ($key !== null) {
                $map[$key] = $val;
            }
        }
        return $map;
    } elseif ($major === 6) { // tag
        $tag = $value;
        $content = cbor_decode($data, $offset);
        return $content;
    } elseif ($major === 7) { // simple values / floats
        if ($value === 20) return false;
        if ($value === 21) return true;
        if ($value === 22) return null;
        if ($value === 31) return null;
        if ($value < 24) return $value;
        if ($value === 25) {
            if ($offset + 2 > strlen($data)) return null;
            $offset += 2;
            return null;
        }
        if ($value === 26) {
            if ($offset + 4 > strlen($data)) return null;
            $offset += 4;
            return null;
        }
        if ($value === 27) {
            if ($offset + 8 > strlen($data)) return null;
            $offset += 8;
            return null;
        }
        return null;
    }

    return null;
}

/**
 * Конвертує публічний ключ з формату COSE (використовується в WebAuthn) 
 * у формат PEM (використовується в PHP/OpenSSL).
 * Наразі підтримується тільки алгоритм EC2 (P-256).
 * 
 * @param array $cose_key Масив з даними ключа
 * @return string|null PEM-рядок або null при помилці
 */
function cose_to_pem($cose_key) {
    if (!isset($cose_key[1]) || $cose_key[1] !== 2) {
        error_log("WebAuthn: COSE ключ не EC2: " . json_encode($cose_key));
        return null;
    }

    $x = isset($cose_key[-2]) ? $cose_key[-2] : null;
    $y = isset($cose_key[-3]) ? $cose_key[-3] : null;

    if ($x === null || $y === null) {
        error_log("WebAuthn: Відсутні X або Y в COSE ключі");
        return null;
    }

    // Підтримка різних форматів входу для координат
    if (is_string($x) && strlen($x) === 32) {
        $x_bin = $x;
    } else {
        $x_bin = base64url_decode($x);
    }
    if (is_string($y) && strlen($y) === 32) {
        $y_bin = $y;
    } else {
        $y_bin = base64url_decode($y);
    }

    if (strlen($x_bin) !== 32 || strlen($y_bin) !== 32) {
        error_log("WebAuthn: Некоректна довжина координат X=" . strlen($x_bin) . " Y=" . strlen($y_bin));
        return null;
    }

    /**
     * Повний префікс DER SubjectPublicKeyInfo для нестиснутої точки EC P-256:
     * SEQUENCE(89) { SEQUENCE(19) { OID ecPublicKey, OID prime256v1 }, BIT STRING(66) { 00 04 X(32) Y(32) } }
     */
    $der_prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004');
    if ($der_prefix === false) {
        error_log("WebAuthn: помилка hex2bin префікса");
        return null;
    }

    $der_binary = $der_prefix . $x_bin . $y_bin;
    
    // Формуємо PEM файл
    $pem = "-----BEGIN PUBLIC KEY-----\n";
    $pem .= chunk_split(base64_encode($der_binary), 64, "\n");
    $pem .= "-----END PUBLIC KEY-----";

    return $pem;
}

/**
 * Верифікує відповідь атестації (реєстрації) від клієнта.
 * Перевіряє челендж, origin, тип операції та витягує публічний ключ.
 * 
 * @param array $attestation_response Дані від navigator.credentials.create
 * @param string $challenge Збережений на сервері челендж
 * @param int $user_id ID користувача
 * @return array Результат верифікації
 */
function verify_attestation_response($attestation_response, $challenge, $user_id) {
    $config = getWebAuthnConfig();
    $origin = $config['origin'];

    // 1. Декодуємо clientDataJSON
    $client_data_json_bytes = base64url_decode($attestation_response['clientDataJSON']);
    $client_data = json_decode($client_data_json_bytes, true);

    // 2. Базові перевірки безпеки
    if (!$client_data || !isset($client_data['type']) || $client_data['type'] !== 'webauthn.create') {
        error_log("WebAuthn: Некоректний тип clientData");
        return ['success' => false, 'error' => 'Некоректний тип clientData'];
    }

    if (!isset($client_data['challenge']) || $client_data['challenge'] !== $challenge) {
        error_log("WebAuthn: Невідповідність челенджу");
        return ['success' => false, 'error' => 'Невідповідність челенджу'];
    }

    if (!isset($client_data['origin']) || strpos($client_data['origin'], $origin) === false) {
        error_log("WebAuthn: Невідповідність origin: " . ($client_data['origin'] ?? 'немає'));
        return ['success' => false, 'error' => 'Невідповідність origin'];
    }

    // 3. Декодуємо attestationObject (CBOR)
    $attestation_object_bytes = base64url_decode($attestation_response['attestationObject']);
    $offset = 0;
    $attestation = cbor_decode($attestation_object_bytes, $offset);
    
    if (!$attestation || !isset($attestation['authData'])) {
        error_log("WebAuthn: Помилка парсингу об'єкта атестації");
        return ['success' => false, 'error' => 'Некоректний об\'єкт атестації'];
    }

    // 4. Парсимо authenticator data (authData)
    $authenticator_bytes = $attestation['authData'];
    if (strlen($authenticator_bytes) < 37) {
        return ['success' => false, 'error' => 'Некоректні дані автентифікатора'];
    }

    $flags = ord($authenticator_bytes[0]);
    $sign_count = unpack('V', substr($authenticator_bytes, 1, 4))[1];

    $has_attested_credential = ($flags & 0x40) !== 0;
    $aaguid = 'unknown';

    // 5. Якщо є дані про ключ, витягуємо AAGUID та сам ключ
    $pubkey_offset = 37;
    if ($has_attested_credential) {
        $aaguid = substr($authenticator_bytes, 37, 16);
        $cred_id_len = unpack('n', substr($authenticator_bytes, 53, 2))[1];
        $pubkey_offset = 55 + $cred_id_len;
    }

    if ($pubkey_offset >= strlen($authenticator_bytes)) {
        return ['success' => false, 'error' => 'Некоректні дані автентифікатора'];
    }

    // 6. Декодуємо публічний ключ (COSE)
    $pubkey_bytes = substr($authenticator_bytes, $pubkey_offset);
    $cose_key = decode_cose_key($pubkey_bytes);
    
    if (!$cose_key) {
        return ['success' => false, 'error' => 'Помилка декодування публічного ключа'];
    }

    // 7. Конвертуємо в PEM для зберігання в БД
    $public_key_pem = cose_to_pem($cose_key);
    if (!$public_key_pem) {
        return ['success' => false, 'error' => 'Помилка конвертації публічного ключа'];
    }

    $credential_id = $attestation_response['id'] ?? null;
    if ($has_attested_credential) {
        $aaguid = bin2hex($aaguid);
    }

    return [
        'success' => true,
        'credential_id' => $credential_id,
        'public_key_pem' => $public_key_pem,
        'counter' => $sign_count,
        'aaguid' => $aaguid,
        'transports' => $attestation_response['transports'] ?? []
    ];
}

/**
 * Допоміжна функція для декодування COSE ключа
 * @param string $pubkey_bytes
 * @return array
 */
function decode_cose_key($pubkey_bytes) {
    $pos = 0;
    $cose = cbor_decode($pubkey_bytes, $pos);

    if (!$cose || !is_array($cose)) {
        error_log("WebAuthn: Помилка декодування COSE");
        return [];
    }

    return $cose;
}

/**
 * Верифікує асерцію (спробу входу) від клієнта.
 * Перевіряє підпис за допомогою збереженого публічного ключа.
 * 
 * @param array $assertion_response Дані від navigator.credentials.get
 * @param string $challenge Збережений на сервері челендж
 * @param string $rp_id Relying Party ID
 * @param object $passkey Об'єкт Passkey з БД
 * @return array Результат верифікації
 */
function verify_assertion_response($assertion_response, $challenge, $rp_id, $passkey) {
    $config = getWebAuthnConfig();
    $origin = $config['origin'];

    // 1. Декодуємо clientDataJSON
    $client_data_json_bytes = base64url_decode($assertion_response['clientDataJSON']);
    $client_data = json_decode($client_data_json_bytes, true);

    // 2. Перевірки безпеки
    if (!$client_data || !isset($client_data['type']) || $client_data['type'] !== 'webauthn.get') {
        error_log("WebAuthn: Некоректний тип clientData для assertion");
        return ['success' => false, 'error' => 'Некоректний тип clientData'];
    }

    if (!isset($client_data['challenge']) || $client_data['challenge'] !== $challenge) {
        error_log("WebAuthn: Невідповідність челенджу assertion");
        return ['success' => false, 'error' => 'Невідповідність челенджу'];
    }

    if (!isset($client_data['origin']) || strpos($client_data['origin'], $origin) === false) {
        error_log("WebAuthn: Невідповідність origin assertion");
        return ['success' => false, 'error' => 'Невідповідність origin'];
    }

    // 3. Отримуємо дані автентифікатора
    $authenticator_data = base64url_decode($assertion_response['authenticatorData']);
    if (strlen($authenticator_data) < 37) {
        return ['success' => false, 'error' => 'Некоректна довжина даних автентифікатора'];
    }

    $flags = ord($authenticator_data[0]);

    // Перевірка присутності користувача (User Presence)
    if (!($flags & 0x01)) {
        return ['success' => false, 'error' => 'Користувач не присутній'];
    }

    $sign_count = unpack('V', substr($authenticator_data, 1, 4))[1];

    // 3.1. Перевірка лічильника підписів (sign counter)
    // Порівнюємо з раніше збереженим значенням для виявлення аномалій.
    // Лічильник 0 означає, що автентифікатор не підтримує його — це допустимо.
    // Якщо нове значення менше або дорівнює збереженому (і збережений > 0) —
    // це сигнал про можливе клонування ключа.
    $stored_counter = (int)$passkey->counter;
    $counter_anomaly = false;

    if ($sign_count !== 0 || $stored_counter !== 0) {
        if ($sign_count <= $stored_counter && $stored_counter > 0) {
            // Аномалія: лічильник не зріс або відкотився — можливо клонований ключ
            $counter_anomaly = true;
            error_log(
                "WebAuthn: АНОМАЛІЯ ЛІЧИЛЬНИКА — credential_id=" . $passkey->credential_id
                . " stored_counter={$stored_counter} new_counter={$sign_count}"
                . " user_id={$passkey->user_id}"
            );
        }
    }

    // 4. Перевірка підпису (Signature)
    $signature = base64url_decode($assertion_response['signature']);
    $client_data_hash = hash('sha256', $client_data_json_bytes, true);
    
    // Дані, які були підписані пристроєм: authenticatorData + hash(clientDataJSON)
    $signed_data = $authenticator_data . $client_data_hash;

    // Завантажуємо публічний ключ з БД
    $public_key = openssl_pkey_get_public($passkey->public_key_pem);
    if (!$public_key) {
        error_log("WebAuthn: Некоректний публічний ключ у passkey");
        return ['success' => false, 'error' => 'Некоректний публічний ключ'];
    }

    // Перевіряємо криптографічний підпис (ECDSA SHA-256)
    $verify = openssl_verify($signed_data, $signature, $public_key, OPENSSL_ALGO_SHA256);

    if ($verify !== 1) {
        error_log("WebAuthn: Помилка перевірки підпису, результат=" . $verify);
        return ['success' => false, 'error' => 'Помилка перевірки підпису'];
    }

    return [
        'success' => true,
        'new_counter' => $sign_count,
        'counter_anomaly' => $counter_anomaly
    ];
}



