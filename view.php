<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/services/PasteService.php';
require_once __DIR__ . '/includes/services/AdQuestService.php';

// Обробка POST-запитів (unlock_paste)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/controllers/PasteController.php';
    $controller = new PasteController();
    $controller->handleRequest();
}

// Отримання ID пасти з параметрів запиту
$id = $_GET['id'] ?? null;
$paste = $id ? Paste::findById($id) : null;

// Отримання користувача з сесії
$user = getCurrentUser();
$is_author = false;
$has_unlocked = false;
$is_admin = ($user && $user->role === 'admin');

// Перевірка пасти
if (!$paste) {
    header("HTTP/1.0 404 Not Found");
} elseif ($paste->isExpired()) {
    header("HTTP/1.0 404 Not Found");
    // $paste залишається, щоб шаблон відрендерив плашку з попередженням про протермінування
} elseif ($paste->is_pending_rewrite) {
    if ($user) {
        $is_author = ($paste->user_id === $user->id);
    }
    if (!$is_author && !$is_admin) {
        header("HTTP/1.0 403 Forbidden");
        $paste = null;
        $_SESSION['error'] = "Ця паста тимчасово недоступна, вона проходить AI-переписування.";
        header("Location: index.php");
        exit;
    }
} elseif (isset($paste->moderation_status) && $paste->moderation_status === 'pending') {
    // Паста очікує результат модерації через OpenAI
    if ($user) {
        $is_author = ($paste->user_id === $user->id);
    }
    if (!$is_author && !$is_admin) {
        header("HTTP/1.0 403 Forbidden");
        $paste = null;
        $_SESSION['error'] = "Ця паста проходить перевірку модерації та незабаром стане доступною.";
        header("Location: index.php");
        exit;
    }
} elseif (isset($paste->moderation_status) && $paste->moderation_status === 'rejected') {
    // Паста відхилена модерацією
    if ($user) {
        $is_author = ($paste->user_id === $user->id);
    }
    if (!$is_author && !$is_admin) {
        header("HTTP/1.0 403 Forbidden");
        $paste = null;
        $_SESSION['error'] = "Ця паста була відхилена модерацією та недоступна.";
        header("Location: index.php");
        exit;
    }
} elseif ($paste->is_private) {
    if ($user) {
        $is_author = ($paste->user_id === $user->id);
    }
    // Серверна перевірка доступу до приватної пасти
    if (!$is_author && !$is_admin) {
        header("HTTP/1.0 403 Forbidden");
        $paste = null; // Ховаємо контент
        $_SESSION['error'] = "У вас немає доступу до цієї приватної пасти.";
        header("Location: index.php");
        exit;
    }
}

if ($paste) {
    if ($user) {
        $is_author = ($paste->user_id === $user->id);
        $has_unlocked = $user->hasUnlocked($paste->id);
    }
    // Рекламний квест прив'язаний до конкретної платної пасти і серверного токена.
    $ad_user_id = $user ? $user->id : null;
    $ad_quest_progress = AdQuestService::progress($paste->id, $ad_user_id);
    $ad_quest_token = AdQuestService::issueToken($paste->id, $ad_user_id);
    $has_ad_access = AdQuestService::hasAccess($paste->id, $ad_user_id);
    $requires_quest = $paste->is_paid && !$is_author && !$is_admin && !$has_unlocked && !$has_ad_access;
    $hide_ads = (!$paste->is_paid && !$paste->is_private);

    // SEO Дані
    $page_title = htmlspecialchars($paste->title);
    $page_description = "Перегляд пасти '" . htmlspecialchars($paste->title) . "' на PayPaste. Автор: " . ($paste->user_id ? "Зареєстрований користувач" : "Анонім");

    // Встановлюємо флаг для шаблону
    $is_locked = PasteService::isLocked($paste, $user) && !$has_ad_access && !$requires_quest;
} else {
    $is_locked = false;
    $requires_quest = false;
    $page_title = "Пасту не знайдено";
}

// Завантаження всіх View і головного шаблону перегляду пасти
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/paste_view.php';
require_once __DIR__ . '/templates/footer.php';
