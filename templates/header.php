<?php require_once __DIR__ . '/../includes/csrf.php'; ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title><?= isset($page_title) ? $page_title . " - PayPaste" : "PayPaste - Швидкий обмін кодом та текстом" ?></title>
    <meta name="description" content="<?= isset($page_description) ? $page_description : "PayPaste - сервіс для збереження тексту та коду в стилі ретро. Купуйте та продавайте доступ до ексклюзивного контенту!" ?>">
    <meta name="keywords" content="pastebin, code share, pay to view, php, mysql, retro web, dimdevoff">
    
    <!-- OpenGraph (Facebook, Telegram, Discord) -->
    <meta property="og:title" content="<?= isset($page_title) ? $page_title : "PayPaste" ?>">
    <meta property="og:description" content="<?= isset($page_description) ? $page_description : "Сервіс обміну текстом у стилі 2000-х" ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= rtrim(getenv('APP_URL') ?: 'https://YOUR_DOMAIN', '/') ?>/assets/img/logo.png">
    <meta property="og:url" content="<?= getenv('APP_URL') ?: 'https://YOUR_DOMAIN' ?>/">

    <!-- Вінтажний Bootstrap 3 -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/img/logo.png" type="image/png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<nav class="navbar navbar-inverse navbar-static-top">
    <div class="container-fluid">
        <!-- Хедер навбара: лого + гамбургер -->
        <div class="navbar-header">
            <!-- Кнопка гамбургера для мобільних -->
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#main-navbar" aria-expanded="false">
                <span class="sr-only">Відкрити навігацію</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand text-warning blink-text" href="index.php">
                <img src="assets/img/logo.png" alt="Logo" style="display:inline-block; height:24px; margin-right:8px; margin-top:-3px;">
                ☠ PAYPASTE☠ 
            </a>
        </div>

        <!-- Колапс-блок (розкривається по кліку на гамбургер) -->
        <div class="collapse navbar-collapse" id="main-navbar">
            <ul class="nav navbar-nav">
                <li><a href="index.php" title="Головна">🏠 Головна</a></li>
                <li><a href="create.php" title="Створити пасту">📝 Нова паста</a></li>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <?php if($currentUser = getCurrentUser()): ?>
                    <li><p class="navbar-text">💰 <?= $currentUser->credits ?> Кредитів</p></li>
                    <li><a href="credits.php" style="color:#ffcc00; font-weight:bold;" title="Поповнити">➕ Поповнити</a></li>
                    <li><a href="settings.php" title="Налаштування">👤 <?= htmlspecialchars($currentUser->nickname) ?></a></li>
                    <li>
                        <form action="login.php" method="POST" style="margin: 8px 15px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="logout">
                            <button class="btn btn-sm btn-danger">Вихід</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="login.php?mode=login"><span class="glyphicon glyphicon-log-in"></span> Вхід</a></li>
                    <li><a href="login.php?mode=register"><span class="glyphicon glyphicon-user"></span> Реєстрація</a></li>
                <?php endif; ?>
            </ul>
        </div><!-- /.navbar-collapse -->
    </div>
</nav>

<div class="container main-content">
    <!-- Рекламний банер -->
    <div class="ad-banner text-center blink-text" style="margin-bottom: 20px;">
        <a href="#">🔥 ТУТ МОЖЕ БУТИ ВАША РЕАЛАМА 🔥 </a>
    </div>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

