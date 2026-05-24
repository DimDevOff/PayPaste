<?php
include_once __DIR__ . "/check_admin.php";
include_once __DIR__ . "/../includes/models/Transaction.php";

// Параметри фільтрації та пагінації
$allowedTypes = ['', 'topup', 'purchase', 'sale', 'creation_fee'];
$filterType   = in_array($_GET['type'] ?? '', $allowedTypes) ? ($_GET['type'] ?? '') : '';
$perPage      = 25;
$currentPage  = max(1, (int) ($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $perPage;

$totalCount = Transaction::count($filterType);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);

$transactions = Transaction::getAll($perPage, $offset, $filterType);

// Мітки типів транзакцій
$typeLabels = [
    'topup'        => ['label' => 'success', 'icon' => '⬆️', 'text' => 'Поповнення'],
    'purchase'     => ['label' => 'danger',  'icon' => '🛒', 'text' => 'Купівля пасти'],
    'sale'         => ['label' => 'info',    'icon' => '💰', 'text' => 'Продаж пасти'],
    'creation_fee' => ['label' => 'warning', 'icon' => '✍️', 'text' => 'Комісія за створення'],
];

// Побудова URL для пагінації/фільтрів
function buildUrl(array $params): string {
    $base = array_filter(['type' => $_GET['type'] ?? '', 'page' => $_GET['page'] ?? '']);
    $merged = array_merge($base, $params);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Транзакції — Admin Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
        .amount-positive { color: #27ae60; font-weight: bold; }
        .amount-negative { color: #c0392b; font-weight: bold; }
        .filter-bar { margin-bottom: 20px; }
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
      <li><a href="moderation.php">🛡️ Модерація</a></li>
      <li><a href="users.php">Користувачі</a></li>
      <li class="active"><a href="transactions.php">💳 Транзакції</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="../index.php">На головний сайт</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-bottom:40px;">
    <h2 class="page-header">
        💳 Історія Транзакцій
        <small><?= number_format($totalCount) ?> записів</small>
    </h2>

    <!-- Фільтри за типом -->
    <div class="filter-bar">
        <div class="btn-group">
            <a href="<?= buildUrl(['type' => '', 'page' => 1]) ?>"
               class="btn btn-sm <?= $filterType === '' ? 'btn-default active' : 'btn-default' ?>">
                Всі
            </a>
            <?php foreach ($typeLabels as $key => $info): ?>
            <a href="<?= buildUrl(['type' => $key, 'page' => 1]) ?>"
               class="btn btn-sm btn-<?= $info['label'] ?> <?= $filterType === $key ? 'active' : '' ?>"
               style="opacity: <?= $filterType === $key ? '1' : '.7' ?>;">
                <?= $info['icon'] ?> <?= $info['text'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Таблиця транзакцій -->
    <div class="panel panel-default">
        <div class="panel-body" style="padding:0;">
            <table class="table table-striped table-hover" style="margin:0;">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Тип</th>
                        <th>Сума</th>
                        <th>Користувач</th>
                        <th>Сервіс</th>
                        <th>Пов'язана Паста</th>
                        <th>Опис</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding:30px;">
                            Транзакцій не знайдено
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                    <?php
                        $info      = $typeLabels[$tx['type']] ?? ['label' => 'default', 'icon' => '?', 'text' => $tx['type']];
                        $isPositive = (int) $tx['amount'] >= 0;
                    ?>
                    <tr>
                        <td><code><?= (int) $tx['id'] ?></code></td>
                        <td>
                            <span class="label label-<?= $info['label'] ?>">
                                <?= $info['icon'] ?> <?= $info['text'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?= $isPositive ? 'amount-positive' : 'amount-negative' ?>">
                                <?= $isPositive ? '+' : '' ?><?= (int) $tx['amount'] ?> кр.
                            </span>
                        </td>
                        <td class="user-cell">
                            <?php if ($tx['nickname']): ?>
                                <strong><?= htmlspecialchars($tx['nickname']) ?></strong>
                                <small><?= htmlspecialchars($tx['email'] ?? '') ?></small>
                            <?php else: ?>
                                <span class="text-muted">[видалено]</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tx['service']): ?>
                                <span class="label label-default"><?= htmlspecialchars($tx['service']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tx['related_paste_id']): ?>
                                <a href="../view.php?id=<?= htmlspecialchars($tx['related_paste_id']) ?>" target="_blank">
                                    <code><?= htmlspecialchars(substr($tx['related_paste_id'], 0, 10)) ?>…</code>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= $tx['description'] ? htmlspecialchars($tx['description']) : '—' ?>
                            </small>
                        </td>
                        <td>
                            <small><?= date('d.m.Y H:i', strtotime($tx['created_at'])) ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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

