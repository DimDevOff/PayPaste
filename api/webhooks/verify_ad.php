<?php
/**
 * Webhook для підтвердження перегляду реклами (Adsterra Quest)
 * Перевіряє підписаний одноразовий токен і зараховує подію на сервері.
 */
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/services/AdQuestService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Помилка безпеки (CSRF). Оновіть сторінку.']);
    exit;
}

$pasteId = trim($_POST['paste_id'] ?? '');
$token = trim($_POST['ad_token'] ?? '');
$userId = $_SESSION['user_id'] ?? null;

if ($pasteId === '' || $token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Відсутній рекламний токен або паста.']);
    exit;
}

$result = AdQuestService::verifyEvent($pasteId, $token, $userId);
if (!$result['success']) {
    http_response_code($result['reason'] === 'rate_limited' ? 429 : 400);
}

echo json_encode($result);
