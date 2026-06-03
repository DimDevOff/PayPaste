<?php
/**
 * API-контролер для роботи з Passkeys (WebAuthn).
 * 
 * Забезпечує:
 * 1. Реєстрацію нових Passkeys (створення челенджу та верифікація відповіді).
 * 2. Вхід в акаунт через біометрію або апаратні ключі.
 * 3. Підтвердження критичних дій (наприклад, видалення акаунта).
 * 4. Управління списком зареєстрованих ключів (максимум 5 на користувача).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    error_log('passkey: session started, id=' . session_id());
}
require_once __DIR__ . '/../includes/models/User.php';
require_once __DIR__ . '/../includes/models/Passkey.php';
require_once __DIR__ . '/../includes/services/AuthService.php';
require_once __DIR__ . '/../includes/repositories/Repo.php';
require_once __DIR__ . '/../includes/webauthn.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'Дію не вказано']);
    exit;
}

try {
    $config = WebAuthn::getConfig();
    $rp_id = $config['rp_id'];
    $origin = $config['origin'];

    switch ($action) {
    // Початок реєстрації: генерація челенджу для пристрою
    case 'register_start':
        $nickname = $_GET['nickname'] ?? 'PasskeyUser';
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

        if ($user_id && Passkey::countByUserId($user_id) >= 5) {
            echo json_encode(['success' => false, 'error' => 'Максимально дозволено 5 ключів']);
            exit;
        }

        $challenge = WebAuthn::generateChallenge(32);
        $_SESSION['webauthn_challenge'] = $challenge;
        $_SESSION['webauthn_user_id'] = $user_id;
        $_SESSION['webauthn_nickname'] = $nickname;

        $user_handle = WebAuthn::base64urlEncode(random_bytes(32));

        // Опції для WebAuthn
        $options = [
            'rp' => [
                'id' => $rp_id,
                'name' => 'PayPaste'
            ],
            'user' => [
                'id' => $user_handle,
                'name' => $user_handle,
                'displayName' => $nickname
            ],
            'challenge' => $challenge,
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257]
            ],
            'timeout' => 60000,
            'authenticatorSelection' => [
                'authenticatorAttachment' => null,
                'userVerification' => 'preferred',
                'residentKey' => 'preferred'
            ],
            'attestation' => 'direct'
        ];

        echo json_encode(['success' => true, 'options' => $options]);
        break;

    // Завершення реєстрації: перевірка відповіді від пристрою та збереження ключа
    case 'register_finish':
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!WebAuthn::verifyCsrfApi($csrf_token)) {
            echo json_encode(['success' => false, 'error' => 'Помилка перевірки CSRF']);
            exit;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['credential'])) {
            error_log("Passkey register_finish: Вхідні дані=" . substr($input, 0, 500));
            echo json_encode(['success' => false, 'error' => 'Відсутні дані ключа']);
            exit;
        }

        $credential = $data['credential'];
        $challenge = $_SESSION['webauthn_challenge'] ?? '';

        if (empty($challenge)) {
            error_log("Passkey register_finish: челендж порожній, сесія=" . json_encode($_SESSION['webauthn_challenge'] ?? null));
            echo json_encode(['success' => false, 'error' => 'Термін дії челенджу вичерпано']);
            exit;
        }

        $user_id = $_SESSION['webauthn_user_id'] ?? null;
        $nickname = $_SESSION['webauthn_nickname'] ?? 'PasskeyUser';

        error_log("Passkey register_finish: ключі облікових даних=" . json_encode(array_keys($credential)));

        $result = WebAuthn::verifyAttestationResponse($credential, $challenge, $user_id);

        error_log("Passkey register_finish: результат=" . json_encode($result));

        if (!$result['success']) {
            error_log("Помилка реєстрації Passkey: " . $result['error']);
            echo json_encode(['success' => false, 'error' => htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8')]);
            exit;
        }

        if (!$user_id) {
            $email = 'passkey_' . substr($result['credential_id'], 0, 16) . '@paypaste.local';
            $random_pass = bin2hex(random_bytes(16));
            $user = AuthService::registerPasskeyUser($email, $random_pass, $nickname);
            $user_id = $user->id;
        }

        AuthService::registerPasskey($user_id, $result);

        AuthService::setSession(User::findById($user_id));
        $_SESSION['success'] = 'Passkey успішно прив\'язано!';

        unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_user_id'], $_SESSION['webauthn_nickname']);

        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        break;

    // Початок входу: генерація челенджу для аутентифікації
    case 'login_start':
        $_SESSION['webauthn_challenge'] = WebAuthn::generateChallenge(32);

        // Опції для WebAuthn
        $options = [
            'challenge' => $_SESSION['webauthn_challenge'],
            'rpId' => $rp_id,
            'timeout' => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => []
        ];

        echo json_encode(['success' => true, 'options' => $options]);
        break;

    // Завершення входу: верифікація підпису пристрою та авторизація сесії
    case 'login_finish':
        // NOTE: СSRF-захист не потрібен — одноразовий challenge вже захищає від CSRF
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['credential'])) {
            echo json_encode(['success' => false, 'error' => 'Відсутні дані ключа']);
            exit;
        }

        $credential = $data['credential'];
        if (!isset($credential['credentialId'])) {
            echo json_encode(['success' => false, 'error' => 'Відсутній credentialId']);
            exit;
        }

        $passkey = Passkey::findByCredentialId($credential['credentialId']);

        if (!$passkey) {
            echo json_encode(['success' => false, 'error' => 'Passkey не знайдено']);
            exit;
        }

        $challenge = $_SESSION['webauthn_challenge'] ?? '';

        if (empty($challenge)) {
            echo json_encode(['success' => false, 'error' => 'Термін дії челенджу вичерпано']);
            exit;
        }

        $result = WebAuthn::verifyAssertionResponse($credential, $challenge, $rp_id, $passkey);

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8')]);
            exit;
        }

        $passkey->updateCounter($result['new_counter']);

        // Аномалія лічильника — сигнал про можливе клонування ключа
        if (!empty($result['counter_anomaly'])) {
            error_log(
                "WebAuthn: Вхід з аномальним лічильником, user_id={$passkey->user_id}"
                . " credential_id={$passkey->credential_id}"
            );
            // Передаємо клієнту попередження — фронтенд може показати повідомлення
            echo json_encode([
                'success' => true,
                'redirect' => 'index.php',
                'warning' => 'Виявлено аномалію автентифікатора. Рекомендуємо перереєструвати passkey.'
            ]);
            unset($_SESSION['webauthn_challenge']);
            break;
        }

        AuthService::passkeyLogin($passkey->credential_id);

        unset($_SESSION['webauthn_challenge']);
        error_log('passkey login_finish: session id=' . session_id() . ' user_id=' . ($_SESSION['user_id'] ?? 'NOT SET'));

        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        break;

    // Підтвердження видалення акаунта: перевірка володіння ключем перед деструктивною дією
    case 'confirm_delete_finish':
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!WebAuthn::verifyCsrfApi($csrf_token)) {
            echo json_encode(['success' => false, 'error' => 'Помилка перевірки CSRF']);
            exit;
        }

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Не авторизовано']);
            exit;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data || !isset($data['credential'])) {
            echo json_encode(['success' => false, 'error' => 'Відсутні дані ключа']);
            exit;
        }

        $credential = $data['credential'];
        $passkey = Passkey::findByCredentialId($credential['credentialId']);

        if (!$passkey || $passkey->user_id !== $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Passkey не знайдено або він вам не належить']);
            exit;
        }

        $challenge = $_SESSION['webauthn_challenge'] ?? '';
        if (empty($challenge)) {
            echo json_encode(['success' => false, 'error' => 'Термін дії челенджу вичерпано']);
            exit;
        }

        $result = WebAuthn::verifyAssertionResponse($credential, $challenge, $rp_id, $passkey);

        if (!$result['success']) {
            echo json_encode(['success' => false, 'error' => htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8')]);
            exit;
        }

        $passkey->updateCounter($result['new_counter']);

        // Аномалія лічильника при підтвердженні деструктивної дії
        if (!empty($result['counter_anomaly'])) {
            error_log(
                "WebAuthn: Аномалія лічильника при confirm_delete, user_id={$passkey->user_id}"
            );
        }

        $_SESSION['passkey_confirmed_delete'] = true;
        
        unset($_SESSION['webauthn_challenge']);

        echo json_encode(['success' => true]);
        break;

    // Видалення конкретного Passkey зі списку користувача
    case 'delete':
        $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!WebAuthn::verifyCsrfApi($csrf_token)) {
            echo json_encode(['success' => false, 'error' => 'Помилка перевірки CSRF']);
            exit;
        }

        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Не авторизовано']);
            exit;
        }

        $passkey_id = $_POST['passkey_id'] ?? '';

        if (empty($passkey_id)) {
            echo json_encode(['success' => false, 'error' => 'Відсутній passkey_id']);
            exit;
        }

        $existing = Passkey::findByUserId($_SESSION['user_id']);
        $found = false;
        foreach ($existing as $pk) {
            if ($pk->id === $passkey_id) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            echo json_encode(['success' => false, 'error' => 'Passkey не знайдено або він вам не належить']);
            exit;
        }

        Passkey::deleteById($passkey_id, $_SESSION['user_id']);

        $_SESSION['success'] = 'Passkey видалено!';

        echo json_encode(['success' => true, 'redirect' => 'settings.php']);
        break;

        default:
            echo json_encode(['success' => false, 'error' => 'Невідома дія']);
    }
} catch (\Throwable $e) {
    error_log("Passkey API помилка: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
    ]);
    exit;
}