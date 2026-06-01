<?php
require_once __DIR__ . "/check_admin.php";
require_once __DIR__ . "/../includes/models/User.php";
require_once __DIR__ . "/../includes/models/Paste.php";
require_once __DIR__ . "/../includes/models/Transaction.php";
require_once __DIR__ . "/../includes/Queue.php";

$totalPastes = Paste::countAll();
$totalUsers  = User::countAll();
$totalMoney  = Transaction::sumTopups(); // Сума всіх поповнень кредитів
$totalTx     = Transaction::count();    // Загальна кількість транзакцій

$queueMetrics = Queue::getMetrics();

// Кількість паст, що очікують ручної модерації
$stmtPending = DB::getInstance()->getPDO()->prepare("SELECT COUNT(*) FROM pastes WHERE moderation_status IN ('pending','moderation_failed')");
$stmtPending->execute();
$modPending = (int)$stmtPending->fetchColumn();

$pageTitle   = 'Панель Адміністратора';
$currentPage = 'index';
$pageStyles  = '.stat-number { font-size: 3.5rem; margin: 0; font-weight: 700; }
        .stat-sub    { font-size: 1.2rem; color: #888; }';
require_once __DIR__ . '/layout/header.php';
?>
    <h2 class="page-header">Загальна Статистика</h2>

    <div class="row text-center">
        <div class="col-md-2">
            <div class="panel panel-info">
                <div class="panel-heading"><h4 class="m-0">📝 Паст</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($totalPastes ?? 0) ?></p>
                    <a href="pastes.php" class="btn btn-info btn-sm" style="margin-top:8px;">Управляти →</a>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel <?= $modPending > 0 ? 'panel-danger' : 'panel-success' ?>">
                <div class="panel-heading"><h4 class="m-0">🛡️ Модерація</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($modPending ?? 0) ?></p>
                    <a href="moderation.php" class="btn btn-sm <?= $modPending > 0 ? 'btn-danger' : 'btn-primary' ?>" style="margin-top:8px;">Переглянути →</a>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-success">
                <div class="panel-heading"><h4 class="m-0">👥 Користувачів</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($totalUsers ?? 0) ?></p>
                    <a href="users.php" class="btn btn-success btn-sm" style="margin-top:8px;">Управляти →</a>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-warning">
                <div class="panel-heading"><h4 class="m-0">💵 Кредитів</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($totalMoney ?? 0) ?></p>
                    <span class="stat-sub">поповнено</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="m-0">📊 Транзакцій</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($totalTx ?? 0) ?></p>
                    <a href="transactions.php" class="btn btn-primary btn-sm" style="margin-top:8px;">Переглянути →</a>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="panel panel-info">
                <div class="panel-heading"><h4 class="m-0">💀 Мертві задачі</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($queueMetrics['dead_count'] ?? 0) ?></p>
                    <a href="queue.php" class="btn btn-info btn-sm" style="margin-top:8px;">Черга →</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Метрики черги задач -->
    <h2 class="page-header">🔄 Черга фонових задач</h2>
    <div class="row text-center">
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-heading"><h4 class="m-0">📬 У черзі</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($queueMetrics['queue_length'] ?? 0) ?></p>
                    <span class="stat-sub">задач очікують</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-danger">
                <div class="panel-heading"><h4 class="m-0">💀 Мертві</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($queueMetrics['dead_count'] ?? 0) ?></p>
                    <span class="stat-sub">не вдалося обробити</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-heading"><h4 class="m-0">🔄 Ретраї</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= number_format($queueMetrics['total_retries'] ?? 0) ?></p>
                    <span class="stat-sub">повторних спроб</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-heading"><h4 class="m-0">⏱️ Сер. час</h4></div>
                <div class="panel-body">
                    <p class="stat-number"><?= $queueMetrics['avg_duration_s'] ?>с</p>
                    <span class="stat-sub">середній час виконання</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($queueMetrics['recent_errors'])): ?>
    <h3>⚠️ Останні помилки</h3>
    <table class="table table-condensed table-striped">
        <thead><tr><th>ID</th><th>Тип</th><th>Помилка</th><th>Спроби</th><th>Створено</th></tr></thead>
        <tbody>
        <?php foreach ($queueMetrics['recent_errors'] as $err): ?>
            <tr class="danger">
                <td><?= htmlspecialchars($err['id']) ?></td>
                <td><?= htmlspecialchars($err['type']) ?></td>
                <td><?= htmlspecialchars(mb_substr($err['last_error'] ?? '', 0, 100)) ?></td>
                <td><?= (int)$err['attempts'] ?></td>
                <td><?= htmlspecialchars($err['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/layout/footer.php';

