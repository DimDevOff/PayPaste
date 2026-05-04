<?php
$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
?>
<h2 style="border-bottom: 2px solid var(--border-color); padding-bottom: 5px;">Нова паста</h2>

<?php if (isset($_SESSION['moderation_failed'])): ?>
<div class="alert alert-danger" style="border: 2px dashed var(--panel-danger-border); transition: all 0.3s ease;">
    <h4 style="margin-top: 0; color: var(--danger);"><i class="glyphicon glyphicon-warning-sign"></i> Текст не пройшов модерацію!</h4>
    <p style="color: var(--text-primary);">На жаль, ваш текст містить ознаки порушення правил спільноти.</p>
    <hr style="border-top: 1px solid var(--border-color);">
    <div class="row">
        <div class="col-md-6">
            <p style="color: var(--text-primary);">Ви можете змінити текст вручну та спробувати ще раз.</p>
        </div>
        <div class="col-md-6 text-right">
            <form action="create.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rewrite_and_publish">
                <input type="hidden" name="content" value="<?= htmlspecialchars($old['content'] ?? '') ?>">
                <input type="hidden" name="title" value="<?= htmlspecialchars($old['title'] ?? '') ?>">
                <input type="hidden" name="is_private" value="<?= isset($old['is_private']) ? '1' : '0' ?>">
                <input type="hidden" name="is_paid" value="<?= isset($old['is_paid']) ? '1' : '0' ?>">
                <input type="hidden" name="view_cost" value="<?= htmlspecialchars($old['view_cost'] ?? '0') ?>">
                <input type="hidden" name="expires_in" value="<?= htmlspecialchars($old['expires_in'] ?? '0') ?>">
                <button type="submit" class="btn btn-warning" style="font-weight: bold; border: 2px solid var(--border-color);">
                    🪄 ПЕРЕФРАЗУВАТИ ТА ОПУБЛІКУВАТИ (AI)
                </button>
            </form>
        </div>
    </div>
</div>
<?php 
    unset($_SESSION['moderation_failed']);
    unset($_SESSION['flagged_categories']);
endif; 
?>

<form action="create.php" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_paste">
    
    <div class="form-group">
        <label>Текст</label>
        <textarea class="form-control" name="content" rows="15" required style="font-family: monospace;"><?= htmlspecialchars($old['content'] ?? '') ?></textarea>
    </div>
    
    <div class="form-group">
        <label>Назва / Тема (опціонально)</label>
        <input type="text" class="form-control" name="title" placeholder="Без назви" value="<?= htmlspecialchars($old['title'] ?? '') ?>">
    </div>

    <div class="form-group block-info">
        <label>📎 Прикріпити файл (до 5 МБ)</label>
        <input type="file" name="attachment" class="form-control" accept="image/png, image/jpeg, image/gif, .pdf, .zip, .txt">
    </div>

    <div class="checkbox">
        <label>
            <input type="checkbox" name="is_private" value="1" <?= (isset($old['is_private']) && $old['is_private'] == '1') ? 'checked' : '' ?>> <strong>Приватна</strong> (тільки за посиланням, сховається з публічного списку)
        </label>
    </div>

    <div class="checkbox block-danger">
        <label class="text-danger">
            <input type="checkbox" name="is_paid" id="is_paid" value="1" <?= (isset($old['is_paid']) && $old['is_paid'] == '1') ? 'checked' : '' ?>> <strong>💲 Платна паста</strong>
            <br><small>Увага: Написання пасти коштує 1 кредит за 10 символів вашого тексту. А також ви зможете встановити ціну за перегляд іншими.</small>
        </label>
        <div id="view_cost_container" style="<?= (isset($old['is_paid']) && $old['is_paid'] == '1') ? 'display:block;' : 'display:none;' ?> margin-top: 10px; padding-left: 20px;">
            <label>Ціна за перегляд (в кредитах):</label>
            <div style="max-width: 200px;">
                <input type="number" class="form-control" name="view_cost" id="view_cost" min="1" value="<?= htmlspecialchars($old['view_cost'] ?? '') ?>" <?= isset($old['is_paid']) ? 'required' : '' ?>>
            </div>
        </div>
    </div>

    <div class="form-group block-info" style="margin-top:10px;">
        <label class="text-primary"><strong>⏰ Час життя пасти</strong></label>
        <select class="form-control" name="expires_in">
            <?php 
                $expires_options = [
                    '0' => '♾️ Вічна (без обмежень)',
                    '10' => '🕐 10 хвилин',
                    '30' => '🕐 30 хвилин',
                    '60' => '🕐 1 година',
                    '360' => '🕐 6 годин',
                    '1440' => '🕐 24 години',
                    '10080' => '🕐 7 днів'
                ];
                $selected_expires = $old['expires_in'] ?? '0';
                foreach($expires_options as $val => $label): 
            ?>
                <option value="<?= $val ?>" <?= $selected_expires == $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <small class="text-muted">Після вибраного часу паста автоматично стане недоступною.</small>
    </div>

    <button type="submit" class="btn btn-success btn-lg btn-block" style="font-weight: bold; font-size: 20px;">ЗБЕРЕГТИ ПАСТУ</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isPaidCheck = document.getElementById('is_paid');
    if (!isPaidCheck) return;
    
    const maxCredits = <?= isset($_SESSION['user_id']) ? User::findById($_SESSION['user_id'])->credits : 0 ?>;
    const contentArea = document.querySelector('textarea[name="content"]');
    const viewCostContainer = document.getElementById('view_cost_container');
    const viewCostInput = document.getElementById('view_cost');
    
    isPaidCheck.addEventListener('change', function() {
        if (this.checked) {
            contentArea.setAttribute('maxlength', maxCredits * 10);
            viewCostContainer.style.display = 'block';
            viewCostInput.setAttribute('required', 'required');
            contentArea.placeholder = `Ви можете ввести максимум ${maxCredits * 10} символів (ваш баланс: ${maxCredits} кр.)`;
        } else {
            contentArea.removeAttribute('maxlength');
            viewCostContainer.style.display = 'none';
            viewCostInput.removeAttribute('required');
            contentArea.placeholder = '';
        }
    });
});
</script>