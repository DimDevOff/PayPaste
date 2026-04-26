<?php
include_once "check_admin.php";
include_once "../includes/models/User.php";
require_once "../includes/csrf.php";

// Параметри пагінації
$perPage     = 25;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$totalCount = User::countAll();
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);

$users = User::getAll($perPage, $offset);

// Побудова URL для пагінації
if (!function_exists('buildUrl')) {
    function buildUrl(array $params): string {
        $base = array_filter(['page' => $_GET['page'] ?? '']);
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
    <title>Користувачі — Admin Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
        .table > tbody > tr > td { vertical-align: middle; }
        .user-cell small { color: #888; display: block; }
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
      <li><a href="pastes.php">Управління Пастами</a></li>
      <li class="active"><a href="users.php">Користувачі</a></li>
      <li><a href="transactions.php">Транзакції</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="../index.php">На головний сайт</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-bottom:40px;">
    <h2 class="page-header">
        👥 Управління Користувачами
        <small><?= number_format($totalCount) ?> записів</small>
    </h2>

    <div class="panel panel-default">
        <div class="panel-body" style="padding:0;">
            <table class="table table-striped table-hover" style="margin:0;">
                <thead>
                    <tr>
                        <th>Користувач</th>
                        <th>Баланс</th>
                        <th>Роль</th>
                        <th>OAuth</th>
                        <th>Зареєстровано</th>
                        <th>Дія</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="user-cell">
                            <strong><?= htmlspecialchars($u['nickname'] ?? 'Anon') ?></strong>
                            <small><?= htmlspecialchars($u['email']) ?></small>
                        </td>
                        <td>
                            <span class="badge"><?= (int) $u['credits'] ?> кр.</span>
                        </td>
                        <td>
                            <?php if (($u['role'] ?? 'user') === 'admin'): ?>
                                <span class="label label-danger">Адмін</span>
                            <?php else: ?>
                                <span class="label label-info">Користувач</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($u['github_id'])): ?>
                                <span class="label label-default">GitHub</span>
                            <?php endif; ?>
                            <?php if (!empty($u['telegram_id'])): ?>
                                <span class="label label-primary">Telegram</span>
                            <?php endif; ?>
                            <?php if (empty($u['github_id']) && empty($u['telegram_id'])): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?= isset($u['created_at']) ? date('d.m.Y H:i', strtotime($u['created_at'])) : '—' ?></small>
                        </td>
                        <td>
                            <?php if (($u['role'] ?? 'user') !== 'admin'): ?>
                                <form action="delete_user.php" method="POST" style="display:inline;"
                                      onsubmit="return confirm('Видалення є незворотнім. Видалити користувача?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-xs">
                                        <span class="glyphicon glyphicon-remove"></span> Видалити
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-default btn-xs" disabled>Заборонено</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding:30px;">
                            Користувачів не знайдено
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
