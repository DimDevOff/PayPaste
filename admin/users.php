<?php
require_once __DIR__ . "/check_admin.php";
include_once __DIR__ . "/../includes/models/User.php";
require_once __DIR__ . "/../includes/csrf.php";

// Параметри фільтрації та пагінації
$search      = trim($_GET['search'] ?? '');
$perPage     = 25;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$totalCount = User::countAll($search);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);

$users = User::getAll($perPage, $offset, $search);

// Побудова URL для пагінації (спільний хелпер)
require_once __DIR__ . '/helpers.php';

$pageTitle   = 'Користувачі — Admin Dashboard';
$currentPage = 'users';
$pageStyles  = '.user-cell small { color: #888; display: block; }';
require_once __DIR__ . '/layout/header.php';
?>
    <div class="row">
        <div class="col-md-6">
            <h2 class="page-header" style="margin-top:0; border:none;">
                👥 Управління Користувачами
                <small><?= number_format($totalCount ?? 0) ?> записів</small>
            </h2>
        </div>
        <div class="col-md-6 text-right">
            <form action="" method="GET" class="form-inline" style="margin-top:20px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Email або Нікнейм..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="submit">
                            <span class="glyphicon glyphicon-search"></span> Пошук
                        </button>
                        <?php if ($search !== ''): ?>
                            <a href="users.php" class="btn btn-default" title="Очистити">
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
                                <form action="delete_user.php" method="POST" style="display:inline;" class="form-confirm-delete-user">
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
            (<?= number_format($totalCount ?? 0) ?> записів)
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

<?php require_once __DIR__ . '/layout/footer.php';

