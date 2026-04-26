<?php
include_once "check_admin.php";
include_once "../includes/models/Paste.php";
include_once "../includes/models/User.php";
require_once "../includes/csrf.php";

// Параметри фільтрації та пагінації
$search      = trim($_GET['search'] ?? '');
$perPage     = 25;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$totalCount = Paste::countAll($search);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);

$pastes = Paste::getAllPastes($perPage, $offset, $search);

// Побудова URL для пагінації
if (!function_exists('buildUrl')) {
    function buildUrl(array $params): string {
        $base = [
            'page' => $_GET['page'] ?? '',
            'search' => $_GET['search'] ?? ''
        ];
        $merged = array_merge($base, $params);
        $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
        return '?' . http_build_query($merged);
    }
}
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
        .pagination { margin: 0; }
        .page-info { line-height: 34px; }
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
    <div class="row">
        <div class="col-md-6">
            <h2 class="page-header" style="margin-top:0; border:none;">
                📝 Управління Пастами
                <small><?= number_format($totalCount) ?> записів</small>
            </h2>
        </div>
        <div class="col-md-6 text-right">
            <form action="" method="GET" class="form-inline" style="margin-top:20px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Заголовок або вміст..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="submit">
                            <span class="glyphicon glyphicon-search"></span> Пошук
                        </button>
                        <?php if ($search !== ''): ?>
                            <a href="pastes.php" class="btn btn-default" title="Очистити">
                                <span class="glyphicon glyphicon-remove"></span>
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
            </form>
        </div>
    </div>
    <hr>

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

    <!-- Пагінація -->
    <?php if ($totalPages > 1): ?>
    <div class="row">
        <div class="col-sm-6 page-info text-muted">
            Сторінка <?= $currentPage ?> з <?= $totalPages ?>
            (<?= number_format($totalCount) ?> записів)
        </div>
        <div class="col-sm-6 text-right">
            <ul class="pagination">
                <?php if ($currentPage > 1): ?>
                <li><a href="<?= buildUrl(['page' => 1]) ?>">&laquo;</a></li>
                <li><a href="<?= buildUrl(['page' => $currentPage - 1]) ?>">&lsaquo;</a></li>
                <?php endif; ?>

                <?php
                $start = max(1, $currentPage - 2);
                $end   = min($totalPages, $currentPage + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                <li class="<?= $p === $currentPage ? 'active' : '' ?>">
                    <a href="<?= buildUrl(['page' => $p]) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                <li><a href="<?= buildUrl(['page' => $currentPage + 1]) ?>">&rsaquo;</a></li>
                <li><a href="<?= buildUrl(['page' => $totalPages]) ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
