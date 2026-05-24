<?php
/**
 * Адмін-сторінка: ручна модерація паст.
 * Показує пасти зі статусами pending та moderation_failed,
 * дозволяє схвалити або відхилити одним кліком.
 */
include_once __DIR__ . "/check_admin.php";
include_once __DIR__ . "/../includes/models/Paste.php";
include_once __DIR__ . "/../includes/models/User.php";
require_once __DIR__ . "/../includes/csrf.php";

$pdo = DB::getInstance()->getPDO();

// Обробка POST-дій (схвалити / відхилити)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $pasteId = $_POST['paste_id'] ?? '';

    if ($pasteId && in_array($action, ['approve', 'reject'], true)) {
        $paste = Paste::findById($pasteId);
        if ($paste) {
            if ($action === 'approve') {
                $paste->moderation_status = 'approved';
                $paste->moderation_result = null;
                $paste->update();
                $_SESSION['success'] = "Пасту {$paste->id} схвалено.";
            } elseif ($action === 'reject') {
                $paste->moderation_status = 'rejected';
                $reason = trim($_POST['reason'] ?? '');
                $paste->moderation_result = $reason
                    ? json_encode(['reason' => 'manual_rejection', 'detail' => $reason], JSON_UNESCAPED_UNICODE)
                    : json_encode(['reason' => 'manual_rejection', 'detail' => 'Відхилено адміністратором вручну'], JSON_UNESCAPED_UNICODE);
                $paste->update();
                $_SESSION['success'] = "Пасту {$paste->id} відхилено.";
            }
        }
    }

    // Після дії — повертаємо на ту ж сторінку
    $redirect = $_POST['redirect'] ?? 'moderation.php';
    header("Location: $redirect");
    exit;
}

// Параметри фільтрації та пагінації
$statusFilter = $_GET['status'] ?? 'needs_review'; // needs_review = pending + moderation_failed
$perPage      = 20;
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($currentPage - 1) * $perPage;

// Побудова WHERE-умови
$where = ["p.moderation_status IN ('pending', 'moderation_failed')"];
$params = [];

if ($statusFilter === 'pending') {
    $where = ["p.moderation_status = 'pending'"];
} elseif ($statusFilter === 'moderation_failed') {
    $where = ["p.moderation_status = 'moderation_failed'"];
}
// 'needs_review' — обидва статуси (дефолт)

$whereSQL = implode(' AND ', $where);

// Підрахунок
$countSQL = "SELECT COUNT(*) FROM pastes p WHERE $whereSQL";
$stmt = $pdo->prepare($countSQL);
$stmt->execute();
$totalCount = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$currentPage = min($currentPage, $totalPages);

// Отримання паст
$pastesSQL = "SELECT p.* FROM pastes p WHERE $whereSQL ORDER BY p.created_at ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($pastesSQL);
$stmt->execute([$perPage, $offset]);
$pastes = $stmt->fetchAll();

// Лічильники для швидких фільтрів
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM pastes WHERE moderation_status = 'pending'")->fetchColumn();
$failedCount = (int)$pdo->query("SELECT COUNT(*) FROM pastes WHERE moderation_status = 'moderation_failed'")->fetchColumn();
$needsReview = $pendingCount + $failedCount;
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Модерація — Адмінпанель</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
        .table > tbody > tr > td { vertical-align: middle; }
        .pagination { margin: 0; }
        .page-info { line-height: 34px; }
        .content-preview {
            max-height: 120px;
            overflow-y: auto;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 8px 12px;
            border-radius: 3px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
        }
        .mod-result {
            font-size: 12px;
            background: #fff3cd;
            padding: 4px 8px;
            border-radius: 3px;
            margin-top: 4px;
        }
        .btn-approve { background-color: #27ae60; border-color: #27ae60; color: #fff; }
        .btn-approve:hover { background-color: #2ecc71; border-color: #2ecc71; color: #fff; }
        .btn-reject { background-color: #c0392b; border-color: #c0392b; color: #fff; }
        .btn-reject:hover { background-color: #e74c3c; border-color: #e74c3c; color: #fff; }
        .reject-reason { display: none; margin-top: 5px; }
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
      <li class="active"><a href="moderation.php">🛡️ Модерация <span class="badge" style="background:#e74c3c;"><?= $needsReview ?></span></a></li>
      <li><a href="users.php">Користувачі</a></li>
      <li><a href="transactions.php">Транзакції</a></li>
      <li><a href="queue.php">Черга задач</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="../index.php">На головний сайт</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-bottom:40px;">
    <h2 class="page-header" style="margin-top:0;">
        🛡️ Ручна модерація
        <small><?= number_format($totalCount) ?> паст на розгляді</small>
    </h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Швидкі фільтри -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-4">
            <a href="moderation.php?status=needs_review" class="btn btn-default btn-block <?= $statusFilter === 'needs_review' ? 'active' : '' ?>">
                📋 Усі на розгляді <span class="badge"><?= $needsReview ?></span>
            </a>
        </div>
        <div class="col-md-4">
            <a href="moderation.php?status=pending" class="btn btn-info btn-block <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                ⏳ Очікують перевірки <span class="badge"><?= $pendingCount ?></span>
            </a>
        </div>
        <div class="col-md-4">
            <a href="moderation.php?status=moderation_failed" class="btn btn-warning btn-block <?= $statusFilter === 'moderation_failed' ? 'active' : '' ?>">
                ⚠️ Збій модерації <span class="badge"><?= $failedCount ?></span>
            </a>
        </div>
    </div>

    <?php if (empty($pastes)): ?>
        <div class="alert alert-success text-center" style="padding:40px;">
            <h3>✅ Черга модерації порожня!</h3>
            <p>Усі пасти перевірені.</p>
        </div>
    <?php else: ?>
    <table class="table table-condensed table-striped table-bordered">
        <thead>
            <tr style="background: #337ab7; color: #fff;">
                <th style="width:50px;">ID</th>
                <th style="width:150px;">Назва</th>
                <th>Контент</th>
                <th style="width:120px;">Автор</th>
                <th style="width:100px;">Статус</th>
                <th style="width:100px;">Дата</th>
                <th style="width:180px;">Дія</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pastes as $p):
            $author = $p['user_id'] ? User::findById($p['user_id']) : null;
            $authorLabel = $author ? htmlspecialchars($author->nickname ?? $author->email) : '<i class="text-muted">Анонім</i>';

            $modResult = json_decode($p['moderation_result'] ?? '[]', true);
            $modDetail = '';
            if (is_array($modResult) && isset($modResult['detail'])) {
                $modDetail = $modResult['detail'];
            } elseif (is_array($modResult) && !isset($modResult['reason'])) {
                // Формат від worker: масив категорій порушень
                $modDetail = implode(', ', $modResult);
            }
        ?>
            <tr>
                <td><code><?= htmlspecialchars(substr($p['id'], 0, 10)) ?></code></td>
                <td><?= htmlspecialchars($p['title']) ?></td>
                <td>
                    <div class="content-preview"><?= htmlspecialchars(mb_substr($p['content'], 0, 500)) ?><?= mb_strlen($p['content']) > 500 ? '…' : '' ?></div>
                    <?php if ($modDetail): ?>
                        <div class="mod-result">🔍 <?= htmlspecialchars($modDetail) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= $authorLabel ?></td>
                <td>
                    <?php if ($p['moderation_status'] === 'moderation_failed'): ?>
                        <span class="label label-warning">⚠️ Збій</span>
                    <?php else: ?>
                        <span class="label label-info">⏳ Очікує</span>
                    <?php endif; ?>
                </td>
                <td><small><?= date('d.m H:i', strtotime($p['created_at'])) ?></small></td>
                <td>
                    <!-- Схвалити -->
                    <form method="POST" style="display:inline;" class="form-approve">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="paste_id" value="<?= htmlspecialchars($p['id']) ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        <button type="submit" class="btn btn-approve btn-xs" title="Схвалити">
                            ✅ Схвалити
                        </button>
                    </form>

                    <!-- Відхилити (з причиною) -->
                    <form method="POST" style="display:inline;" class="reject-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="paste_id" value="<?= htmlspecialchars($p['id']) ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                        <button type="button" class="btn btn-reject btn-xs" title="Відхилити" data-toggle-reason>
                            ❌ Відхилити
                        </button>
                        <div class="reject-reason">
                            <input type="text" name="reason" class="form-control input-xs" placeholder="Причина (опціонально)" style="display:inline-block; width:140px; height:24px; font-size:11px;">
                            <button type="submit" class="btn btn-danger btn-xs">Підтвердити</button>
                        </div>
                    </form>

                    <a href="../view.php?id=<?= htmlspecialchars($p['id']) ?>" target="_blank" class="btn btn-default btn-xs" title="Переглянути повністю">
                        <span class="glyphicon glyphicon-eye-open"></span>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

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
                <li><a href="?status=<?= urlencode($statusFilter) ?>&page=1">&laquo;</a></li>
                <li><a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $currentPage - 1 ?>">&lsaquo;</a></li>
                <?php endif; ?>
                <?php
                $start = max(1, $currentPage - 2);
                $end   = min($totalPages, $currentPage + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <li class="<?= $i === $currentPage ? 'active' : '' ?>">
                    <a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                <li><a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $currentPage + 1 ?>">&rsaquo;</a></li>
                <li><a href="?status=<?= urlencode($statusFilter) ?>&page=<?= $totalPages ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<script nonce="<?= csp_nonce() ?>">
// Обробники подій (замість inline onclick/onsubmit)
document.addEventListener('click', function(e) {
    var toggleBtn = e.target.closest('[data-toggle-reason]');
    if (toggleBtn) {
        var form = toggleBtn.closest('.reject-form');
        var reasonDiv = form.querySelector('.reject-reason');
        reasonDiv.style.display = reasonDiv.style.display === 'none' || !reasonDiv.style.display ? 'block' : 'none';
    }
});

document.addEventListener('submit', function(e) {
    // Схвалити — підтвердження
    if (e.target.classList.contains('form-approve')) {
        if (!confirm('Схвалити цю пасту?')) {
            e.preventDefault();
        }
    }
    // Відхилити — підтвердження
    if (e.target.classList.contains('reject-form')) {
        if (!confirm('Відхилити цю пасту?')) {
            e.preventDefault();
        }
    }
});
</script>

</body>
</html>
