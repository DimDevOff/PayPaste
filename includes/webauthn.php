<?php
/**
 * Клас WebAuthn (FIDO2) — без зовнішніх залежностей.
 *
 * Містить усю логіку: CBOR парсинг, COSE→PEM, верифікацію атестації та асерції.
 */
require_once __DIR__ . '/../config/config.php';

class WebAuthn {

    public static function base64urlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function generateChallenge(int $length = 32): string {
        return self::base64urlEncode(random_bytes($length));
    }

    public static function verifyCsrfApi(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function getConfig(): array {
        $app_url = defined('APP_URL') && APP_URL ? APP_URL : 'https://YOUR_DOMAIN';
        $rp_id = defined('WEBAUTHN_RP_ID') && WEBAUTHN_RP_ID ? WEBAUTHN_RP_ID : parse_url($app_url, PHP_URL_HOST);
        return [
            'rp_id'   => $rp_id,
            'rp_name' => 'PayPaste',
            'origin'  => defined('WEBAUTHN_ORIGIN') && WEBAUTHN_ORIGIN ? WEBAUTHN_ORIGIN : $app_url,
        ];
    }

    public static function cborDecode(string $data, &$offset = 0) {
        if ($offset >= strlen($data)) return null;
        $byte  = ord($data[$offset]);
        $major = ($byte >> 5) & 0x07;
        $minor = $byte & 0x1F;
        $offset++;
        $value = null;
        if ($minor < 24) $value = $minor;
        elseif ($minor === 24) {
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
        } elseif ($minor === 31) return null;

        if ($major === 0) return $value;
        if ($major === 1) return -1 - $value;
        if ($major === 2) {
            if ($offset + $value > strlen($data)) return null;
            $bytes = substr($data, $offset, $value);
            $offset += $value;
            return $bytes;
        }
        if ($major === 3) {
            if ($offset + $value > strlen($data)) return null;
            $str = substr($data, $offset, $value);
            $offset += $value;
            return $str;
        }
        if ($major === 4) {
            $arr = [];
            for ($i = 0; $i < $value; $i++) $arr[] = self::cborDecode($data, $offset);
            return $arr;
        }
        if ($major === 5) {
            $map = [];
            for ($i = 0; $i < $value; $i++) {
                $key = self::cborDecode($data, $offset);
                $val = self::cborDecode($data, $offset);
                if ($key !== null) $map[$key] = $val;
            }
            return $map;
        }
        if ($major === 6) return self::cborDecode($data, $offset);
        if ($major === 7) {
            if ($value === 20) return false;
            if ($value === 21) return true;
            if ($value === 22 || $value === 31) return null;
            if ($value < 24) return $value;
            if ($value === 25) { $offset += 2; return null; }
            if ($value === 26) { $offset += 4; return null; }
            if ($value === 27) { $offset += 8; return null; }
        }
        return null;
    }

    public static function coseToPem(array $coseKey): ?string {
        if (!isset($coseKey[1]) || $coseKey[1] !== 2) {
            error_log("WebAuthn: COSE ключ не EC2");
            return null;
        }
        $x = $coseKey[-2] ?? null;
        $y = $coseKey[-3] ?? null;
        if ($x === null || $y === null) return null;

        $x_bin = is_string($x) && strlen($x) === 32 ? $x : self::base64urlDecode($x);
        $y_bin = is_string($y) && strlen($y) === 32 ? $y : self::base64urlDecode($y);
        if (strlen($x_bin) !== 32 || strlen($y_bin) !== 32) return null;

        $der_prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004');
        if ($der_prefix === false) return null;
        $pem  = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($der_prefix . $x_bin . $y_bin), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----";
        return $pem;
    }

    public static function decodeCoseKey(string $pubkeyBytes): array {
        $pos = 0;
        $cose = self::cborDecode($pubkeyBytes, $pos);
        return (!$cose || !is_array($cose)) ? [] : $cose;
    }

    public static function verifyAttestationResponse(array $response, string $challenge, string $userId): array {
        $config = self::getConfig();
        $origin = $config['origin'];
        $clientDataJsonBytes = self::base64urlDecode($response['clientDataJSON']);
        $clientData = json_decode($clientDataJsonBytes, true);

        if (!$clientData || !isset($clientData['type']) || $clientData['type'] !== 'webauthn.create')
            return ['success' => false, 'error' => 'Некоректний тип clientData'];
        if (!isset($clientData['challenge']) || $clientData['challenge'] !== $challenge)
            return ['success' => false, 'error' => 'Невідповідність челенджу'];
        if (!isset($clientData['origin']) || rtrim($clientData['origin'], '/') !== rtrim($origin, '/'))
            return ['success' => false, 'error' => 'Невідповідність origin'];

        $attestationObjectBytes = self::base64urlDecode($response['attestationObject']);
        $offset = 0;
        $attestation = self::cborDecode($attestationObjectBytes, $offset);
        if (!$attestation || !isset($attestation['authData']))
            return ['success' => false, 'error' => 'Некоректний об\'єкт атестації'];

        $authenticatorBytes = $attestation['authData'];
        if (strlen($authenticatorBytes) < 37)
            return ['success' => false, 'error' => 'Некоректні дані автентифікатора'];

        $signCount = unpack('V', substr($authenticatorBytes, 1, 4))[1];
        $hasAttestedCredential = (ord($authenticatorBytes[0]) & 0x40) !== 0;
        $aaguid = 'unknown';
        $pubkeyOffset = 37;

        if ($hasAttestedCredential) {
            $aaguid = substr($authenticatorBytes, 37, 16);
            $credIdLen = unpack('n', substr($authenticatorBytes, 53, 2))[1];
            $pubkeyOffset = 55 + $credIdLen;
        }

        if ($pubkeyOffset >= strlen($authenticatorBytes))
            return ['success' => false, 'error' => 'Некоректні дані автентифікатора'];

        $coseKey = self::decodeCoseKey(substr($authenticatorBytes, $pubkeyOffset));
        if (empty($coseKey))
            return ['success' => false, 'error' => 'Помилка декодування публічного ключа'];

        $publicKeyPem = self::coseToPem($coseKey);
        if (!$publicKeyPem)
            return ['success' => false, 'error' => 'Помилка конвертації публічного ключа'];

        return [
            'success'        => true,
            'credential_id'  => $response['id'] ?? null,
            'public_key_pem' => $publicKeyPem,
            'counter'        => $signCount,
            'aaguid'         => $hasAttestedCredential ? bin2hex($aaguid) : $aaguid,
            'transports'     => $response['transports'] ?? [],
        ];
    }

    public static function verifyAssertionResponse(array $response, string $challenge, string $rpId, object $passkey): array {
        $config = self::getConfig();
        $origin = $config['origin'];
        $clientDataJsonBytes = self::base64urlDecode($response['clientDataJSON']);
        $clientData = json_decode($clientDataJsonBytes, true);

        if (!$clientData || !isset($clientData['type']) || $clientData['type'] !== 'webauthn.get')
            return ['success' => false, 'error' => 'Некоректний тип clientData'];
        if (!isset($clientData['challenge']) || $clientData['challenge'] !== $challenge)
            return ['success' => false, 'error' => 'Невідповідність челенджу'];
        if (!isset($clientData['origin']) || rtrim($clientData['origin'], '/') !== rtrim($origin, '/'))
            return ['success' => false, 'error' => 'Невідповідність origin'];

        $authenticatorData = self::base64urlDecode($response['authenticatorData']);
        if (strlen($authenticatorData) < 37)
            return ['success' => false, 'error' => 'Некоректна довжина даних автентифікатора'];

        $flags = ord($authenticatorData[0]);
        if (!($flags & 0x01))
            return ['success' => false, 'error' => 'Користувач не присутній'];

        $signCount = unpack('V', substr($authenticatorData, 1, 4))[1];
        $storedCounter = (int)$passkey->counter;
        $counterAnomaly = false;

        if ($signCount !== 0 || $storedCounter !== 0) {
            if ($signCount <= $storedCounter && $storedCounter > 0) {
                $counterAnomaly = true;
                error_log("WebAuthn: АНОМАЛІЯ ЛІЧИЛЬНИКА stored={$storedCounter} new={$signCount}");
            }
        }

        $signature = self::base64urlDecode($response['signature']);
        $clientDataHash = hash('sha256', $clientDataJsonBytes, true);
        $publicKey = openssl_pkey_get_public($passkey->public_key_pem);
        if (!$publicKey)
            return ['success' => false, 'error' => 'Некоректний публічний ключ'];

        if (openssl_verify($authenticatorData . $clientDataHash, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1)
            return ['success' => false, 'error' => 'Помилка перевірки підпису'];

        return ['success' => true, 'new_counter' => $signCount, 'counter_anomaly' => $counterAnomaly];
    }
}
