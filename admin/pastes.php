<?php
include_once "check_admin.php";
include_once "../includes/models/Paste.php";
include_once "../includes/models/User.php";
require_once "../includes/csrf.php";

$pastes = Paste::getAllPastes();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Управління пастами — Admin Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
        .table > tbody > tr > td { vertical-align: middle; }
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
      <li><a href="index.php">Статистика</a></li>
      <li class="active"><a href="pastes.php">Управління Пастами</a></li>
      <li><a href="users.php">Користувачі</a></li>
      <li><a href="transactions.php">Транзакції</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="../index.php">На головний сайт</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-bottom:40px;">
    <h2 class="page-header">
        📝 Управління Пастами
        <small><?= count($pastes) ?> записів</small>
    </h2>

    <div class="panel panel-default">
        <div class="panel-body" style="padding:0;">
            <table class="table table-striped table-hover" style="margin:0;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Назва</th>
                        <th>Автор</th>
                        <th>Дата</th>
                        <th>Доступ / Ціна</th>
                        <th>Дія</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pastes as $p): ?>
                <?php
                    $u = $p['user_id'] ? User::findById($p['user_id']) : null;
                    $authorLabel = $u ? htmlspecialchars($u->email) : '<i class="text-muted">Anon/Guest</i>';
                ?>
                    <tr>
                        <td><code><?= htmlspecialchars(substr($p['id'], 0, 10)) ?>…</code></td>
                        <td><?= htmlspecialchars($p['title']) ?></td>
                        <td><?= $authorLabel ?></td>
                        <td><small><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></small></td>
                        <td>
                            <?php if ($p['is_paid']): ?>
                                <span class="label label-warning">💰 <?= (int) ($p['view_cost'] ?? 0) ?> кр.</span>
                            <?php else: ?>
                                <span class="label label-success">Безкоштовно</span>
                            <?php endif; ?>
                            <?php if ($p['is_private']): ?>
                                <span class="label label-default">🔒 Приватна</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="../view.php?id=<?= htmlspecialchars($p['id']) ?>" target="_blank"
                               class="btn btn-default btn-xs">
                                <span class="glyphicon glyphicon-eye-open"></span>
                            </a>
                            <form action="delete_paste.php" method="POST" style="display:inline;"
                                  onsubmit="return confirm('Ви впевнені, що хочете видалити цю пасту?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                                <button type="submit" class="btn btn-danger btn-xs">
                                    <span class="glyphicon glyphicon-trash"></span>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pastes)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding:30px;">
                            Немає збережених паст
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
