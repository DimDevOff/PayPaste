<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/controllers/PasteController.php';

$controller = new PasteController();
$controller->handleRequest();

// ── Підготовка даних для шаблону (винесено з templates/home.php) ──
$cat = $_GET['category'] ?? 'all';
$tag = $_GET['tag'] ?? '';
$pastes = Paste::findAllPublic(20, $cat, $tag);
$popularTags = Paste::getPopularTags(15);

// Попереднє завантаження тегів для кожної пасти (щоб не робити SQL у шаблоні)
$paste_tags = [];
foreach ($pastes as $p) {
    $pt = $p->getTagsByPopularity();
    $paste_tags[$p->id] = [
        'visible' => array_slice($pt, 0, 3),
        'hidden'  => array_slice($pt, 3),
        'has_more' => count($pt) > 3,
    ];
}

// Завантаження всіх View і головного шаблону домашньої сторінки
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/home.php';
require_once __DIR__ . '/templates/footer.php';
