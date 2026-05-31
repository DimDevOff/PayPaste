<?php header('Content-Type: text/html; charset=UTF-8'); ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin Dashboard') ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
        .table > tbody > tr > td { vertical-align: middle; }
        .pagination { margin: 0; }
        .page-info { line-height: 34px; }
        <?= $pageStyles ?? '' ?>
    </style>
</head>
<body>

<!-- Навігація -->
<nav class="navbar navbar-inverse" style="border-radius:0;">
  <div class="container">
    <div class="navbar-header">
      <a class="navbar-brand" href="index.php">🛡️ Admin Dashboard</a>
    </div>
    <ul class="nav navbar-nav">
      <li<?= ($currentPage ?? '') === 'index' ? ' class="active"' : '' ?>><a href="index.php">Статистика</a></li>
      <li<?= ($currentPage ?? '') === 'pastes' ? ' class="active"' : '' ?>><a href="pastes.php">Управління Пастами</a></li>
      <li<?= ($currentPage ?? '') === 'moderation' ? ' class="active"' : '' ?>><a href="moderation.php">🛡️ Модерація<?= !empty($navModBadge) ? ' <span class="badge" style="background:#e74c3c;">'.(int)$navModBadge.'</span>' : '' ?></a></li>
      <li<?= ($currentPage ?? '') === 'users' ? ' class="active"' : '' ?>><a href="users.php">Користувачі</a></li>
      <li<?= ($currentPage ?? '') === 'transactions' ? ' class="active"' : '' ?>><a href="transactions.php">Транзакції</a></li>
      <li<?= ($currentPage ?? '') === 'queue' ? ' class="active"' : '' ?>><a href="queue.php">Черга задач</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="../index.php">На головний сайт</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-bottom:40px;">
