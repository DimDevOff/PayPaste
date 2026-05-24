<?php
/**
 * Адмін-сторінка: моніторинг черги фонових задач.
 */
require_once __DIR__ . "/check_admin.php";
include_once __DIR__ . "/../includes/Queue.php";

$metrics = Queue::getMetrics();
$pdo = DB::getInstance()->getPDO();

// Список задач за статусами
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = "status = :status";
    $params[':status'] = $statusFilter;
}
if ($typeFilter !== 'all') {
    $where[] = "type = :type";
    $params[':type'] = $typeFilter;
}

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Підрахунок
$countSQL = "SELECT COUNT(*) FROM jobs $whereSQL";
$stmt = $pdo->prepare($countSQL);
$stmt->execute($params);
$totalJobs = (int)$stmt->fetchColumn();

// Отримання задач
$jobsSQL = "SELECT * FROM jobs $whereSQL ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($jobsSQL);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll();

$totalPages = max(1, ceil($totalJobs / $perPage));

// Типи задач для фільтра
$jobTypes = Queue::getTypes();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Черга задач — Адмінпанель</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
        .status-queued { color: #31708f; font-weight: bold; }
        .status-processing { color: #8a6d3b; font-weight: bold; }
        .status-completed { color: #3c763d; font-weight: bold; }
        .status-failed { color: #a94442; font-weight: bold; }
        .status-dead { color: #777; font-weight: bold; text-decoration: line-through; }
    </style>
</head>
<body>

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
      <li><a href="transactions.php">Транзакції</a></li>
      <li class="active"><a href="queue.php">Черга задач</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="../index.php">На головний сайт</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-bottom:40px;">
    <h2 class="page-header">🔄 Черга фонових задач</h2>

    <!-- Метрики -->
    <div class="row text-center" style="margin-bottom: 20px;">
        <div class="col-md-2">
            <div class="panel panel-info">
                <div class="panel-body">
                    <h3><?= number_format($metrics['queue_length'] ?? 0) ?></h3>
                    <small>В черзі</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-warning">
                <div class="panel-body">
                    <h3><?= number_format($metrics['by_status']['processing'] ?? 0) ?></h3>
                    <small>Обробляються</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-success">
                <div class="panel-body">
                    <h3><?= number_format($metrics['by_status']['completed'] ?? 0) ?></h3>
                    <small>Завершені</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-danger">
                <div class="panel-body">
                    <h3><?= number_format($metrics['by_status']['failed'] ?? 0) ?></h3>
                    <small>Помилки</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h3><?= number_format($metrics['dead_count'] ?? 0) ?></h3>
                    <small>Мертві</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-primary">
                <div class="panel-body">
                    <h3><?= $metrics['avg_duration_s'] ?>с</h3>
                    <small>Сер. час</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Фільтри -->
    <form method="GET" class="form-inline" style="margin-bottom: 15px;">
        <select name="status" class="form-control">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Всі статуси</option>
            <option value="queued" <?= $statusFilter === 'queued' ? 'selected' : '' ?>>В черзі</option>
            <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Обробка</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Завершено</option>
            <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Помилка</option>
            <option value="dead" <?= $statusFilter === 'dead' ? 'selected' : '' ?>>Мертва</option>
        </select>
        <select name="type" class="form-control">
            <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Всі типи</option>
            <?php foreach ($jobTypes as $t): ?>
                <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Фільтрувати</button>
        <a href="queue.php" class="btn btn-default">Скинути</a>
    </form>

    <!-- Таблиця задач -->
    <?php if (empty($jobs)): ?>
        <div class="alert alert-info">Задач не знайдено.</div>
    <?php else: ?>
    <table class="table table-condensed table-striped table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Тип</th>
                <th>Статус</th>
                <th>Спроби</th>
                <th>Плановано</th>
                <th>Почато</th>
                <th>Завершено</th>
                <th>Помилка</th>
                <th>Створено</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($jobs as $j): ?>
            <tr>
                <td><code><?= htmlspecialchars($j['id']) ?></code></td>
                <td><?= htmlspecialchars($j['type']) ?></td>
                <td><span class="status-<?= $j['status'] ?>"><?= htmlspecialchars($j['status']) ?></span></td>
                <td><?= (int)$j['attempts'] ?>/<?= (int)$j['max_attempts'] ?></td>
                <td><?= htmlspecialchars($j['scheduled_at'] ?? '-') ?></td>
                <td><?= htmlspecialchars($j['started_at'] ?? '-') ?></td>
                <td><?= htmlspecialchars($j['completed_at'] ?? '-') ?></td>
                <td title="<?= htmlspecialchars($j['last_error'] ?? '') ?>">
                    <?= htmlspecialchars(mb_substr($j['last_error'] ?? '', 0, 60)) ?>
                </td>
                <td><?= htmlspecialchars($j['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Пагінація -->
    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="<?= $i === $page ? 'active' : '' ?>">
                    <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&type=<?= urlencode($typeFilter) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

</body>
</html>
