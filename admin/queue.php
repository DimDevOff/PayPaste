<?php
/**
 * Адмін-сторінка: моніторинг черги фонових задач.
 */
require_once __DIR__ . "/check_admin.php";

$metrics = Queue::getMetrics();

// Список задач за статусами
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$totalJobs = Queue::countJobs($statusFilter, $typeFilter);
$jobs = Queue::listJobs($statusFilter, $typeFilter, $page, $perPage);
$totalPages = max(1, ceil($totalJobs / $perPage));

// Типи задач для фільтра
$jobTypes = Queue::getTypes();

$pageTitle   = 'Черга задач — Адмінпанель';
$currentPage = 'queue';
$pageStyles  = '
        .status-queued { color: #31708f; font-weight: bold; }
        .status-processing { color: #8a6d3b; font-weight: bold; }
        .status-completed { color: #3c763d; font-weight: bold; }
        .status-failed { color: #a94442; font-weight: bold; }
        .status-dead { color: #777; font-weight: bold; text-decoration: line-through; }';
require_once __DIR__ . '/layout/header.php';
?>
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

<?php require_once __DIR__ . '/layout/footer.php';
