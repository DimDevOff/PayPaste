<?php
include_once __DIR__ . "/check_admin.php";
include_once __DIR__ . "/../includes/models/Paste.php";
require_once __DIR__ . "/../includes/csrf.php";

$paste_id = $_GET['id'] ?? ($_POST['id'] ?? '');

if (empty($paste_id)) {
    die('ID пасти не вказано.');
}

$paste = Paste::findById($paste_id);
if (!$paste) {
    die('Паста не знайдена.');
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $paste->title = trim($_POST['title'] ?? '');
    $paste->content = trim($_POST['content'] ?? '');
    $paste->is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $paste->is_private = isset($_POST['is_private']) ? 1 : 0;
    $paste->view_cost = isset($_POST['is_paid']) ? (int)($_POST['view_cost'] ?? 0) : 0;
    
    $expires_at = $_POST['expires_at'] ?? '';
    if (!empty($expires_at)) {
        // перетворення формату 'Y-m-d\TH:i' у 'Y-m-d H:i:s'
        $paste->expires_at = date('Y-m-d H:i:s', strtotime($expires_at));
    } else {
        $paste->expires_at = null;
    }

    if (empty($paste->content)) {
        $error_msg = 'Контент не може бути порожнім.';
    } else {
        // Оновлення статусу модерації з валідацією
        $newModStatus = $_POST['moderation_status'] ?? null;
        $allowedStatuses = ['pending', 'approved', 'rejected', 'moderation_failed'];
        if ($newModStatus && in_array($newModStatus, $allowedStatuses, true)) {
            $paste->moderation_status = $newModStatus;
        }

        $paste->update();
        $success_msg = 'Паста успішно оновлена!';
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Редагування пасти — Admin Dashboard</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
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
      <li class="active"><a href="pastes.php">Управління Пастами</a></li>
      <li><a href="moderation.php">🛡️ Модерація</a></li>
      <li><a href="users.php">Користувачі</a></li>
      <li><a href="transactions.php">Транзакції</a></li>
    </ul>
    <ul class="nav navbar-nav navbar-right">
      <li><a href="../index.php">На головний сайт</a></li>
    </ul>
  </div>
</nav>

<div class="container" style="padding-bottom:40px;">
    <h2 class="page-header" style="margin-top:0;">📝 Редагування пасти <?= htmlspecialchars($paste->id) ?></h2>
    
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="panel panel-default">
        <div class="panel-body">
            <form action="edit_paste.php?id=<?= htmlspecialchars($paste->id) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($paste->id) ?>">
                
                <div class="form-group">
                    <label>Назва / Тема</label>
                    <input type="text" class="form-control" name="title" value="<?= htmlspecialchars($paste->title) ?>">
                </div>

                <div class="form-group">
                    <label>Текст (Content)</label>
                    <textarea class="form-control" name="content" rows="15" required style="font-family: monospace;"><?= htmlspecialchars($paste->content) ?></textarea>
                </div>

                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="is_private" value="1" <?= $paste->is_private ? 'checked' : '' ?>> <strong>Приватна</strong>
                    </label>
                </div>

                <div class="checkbox" style="background:#ffeeee; padding:10px; border:1px dashed red;">
                    <label class="text-danger">
                        <input type="checkbox" name="is_paid" id="is_paid" value="1" <?= $paste->is_paid ? 'checked' : '' ?>> <strong>💲 Платна паста</strong>
                    </label>
                    <div id="view_cost_container" style="<?= $paste->is_paid ? 'display:block;' : 'display:none;' ?> margin-top: 10px;">
                        <label>Ціна за перегляд (в кредитах):</label>
                        <div class="col-xs-12 col-sm-4" style="padding-left:0;">
                            <input type="number" class="form-control" name="view_cost" id="view_cost" min="1" value="<?= htmlspecialchars((string)($paste->view_cost ?? '0')) ?>" <?= $paste->is_paid ? 'required' : '' ?>>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>

                <div class="form-group" style="background:#eef5ff; padding:10px; border:1px dashed #337ab7; margin-top:10px;">
                    <label class="text-primary"><strong>⏰ Дата закінчення (Expires at)</strong></label>
                    <input type="datetime-local" class="form-control" name="expires_at" value="<?= $paste->expires_at ? date('Y-m-d\TH:i', strtotime($paste->expires_at)) : '' ?>">
                    <small class="text-muted">Залиште порожнім, щоб паста була вічною.</small>
                </div>

                <div class="form-group" style="background:#fff8e1; padding:10px; border:1px dashed #f0ad4e; margin-top:10px;">
                    <label class="text-warning"><strong>🛡️ Статус модерації</strong></label>
                    <select class="form-control" name="moderation_status">
                        <?php
                        $statuses = [
                            'pending'           => '⏳ Очікує перевірки (pending)',
                            'approved'          => '✅ Схвалено (approved)',
                            'rejected'          => '❌ Відхилено (rejected)',
                            'moderation_failed' => '⚠️ Модерація не завершена (moderation_failed)'
                        ];
                        $current = $paste->moderation_status ?? 'pending';
                        foreach ($statuses as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $current === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (($paste->moderation_status ?? '') === 'moderation_failed'): ?>
                        <small class="text-danger"><strong>Увага:</strong> Модерація цієї пасти не завершилася через збій сервісу. Перевірте контент вручну перед схваленням.</small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Зберегти зміни</button>
                <a href="pastes.php" class="btn btn-default">Повернутися до списку</a>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isPaidCheck = document.getElementById('is_paid');
    const viewCostContainer = document.getElementById('view_cost_container');
    const viewCostInput = document.getElementById('view_cost');
    
    isPaidCheck.addEventListener('change', function() {
        if (this.checked) {
            viewCostContainer.style.display = 'block';
            viewCostInput.setAttribute('required', 'required');
        } else {
            viewCostContainer.style.display = 'none';
            viewCostInput.removeAttribute('required');
        }
    });
});
</script>
</body>
</html>

