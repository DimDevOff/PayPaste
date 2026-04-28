<?php
include_once __DIR__ . "/check_admin.php";
include_once __DIR__ . "/../includes/models/User.php";
include_once __DIR__ . "/../includes/models/Paste.php";
include_once __DIR__ . "/../includes/models/Transaction.php";

$totalPastes = Paste::countAll();
$totalUsers  = User::countAll();
$totalMoney  = Transaction::sumTopups(); // Сума всіх поповнень кредитів
$totalTx     = Transaction::count();    // Загальна кількість транзакцій
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Панель Адміністратора</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
        .stat-number { font-size: 3.5rem; margin: 0; font-weight: 700; }
        .stat-sub    { font-size: 1.2rem; color: #888; }
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
      <li class="active"><a href="index.php">Статистика</a></li>
      <li><a href="pastes.php">Управління Пастами</a></li>
      <li><a href="users.php">Користувачі</a></li>
      <li><a href="transactions.php">Транзакції</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="../index.php">На головний сайт</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-bottom:40px;">
    <h2 class="page-header">Загальна Статистика</h2>

    <div class="row text-center">
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-heading"><h4 class="m-0">📝 Всього Паст</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($totalPastes) ?></p>
                    <a href="pastes.php" class="btn btn-info btn-sm" style="margin-top:8px;">Управляти →</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-heading"><h4 class="m-0">👥 Користувачів</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($totalUsers) ?></p>
                    <a href="users.php" class="btn btn-success btn-sm" style="margin-top:8px;">Управляти →</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-heading"><h4 class="m-0">💵 Кредитів поповнено</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($totalMoney) ?></p>
                    <span class="stat-sub">кредитів</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="m-0">📊 Транзакцій</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($totalTx) ?></p>
                    <a href="transactions.php" class="btn btn-default btn-sm" style="margin-top:8px;">Переглянути →</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

