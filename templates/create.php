<h2 style="border-bottom: 2px solid #ccc; padding-bottom: 5px;">Нова паста</h2>
<form action="create.php" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_paste">
    
    <div class="form-group">
        <label>Текст</label>
        <textarea class="form-control" name="content" rows="15" required style="font-family: monospace; background: #fafafa;"></textarea>
    </div>
    
    <div class="form-group">
        <label>Назва / Тема (опціонально)</label>
        <input type="text" class="form-control" name="title" placeholder="Без назви">
    </div>

    <div class="form-group" style="background:#f9f9f9; padding:10px; border:1px solid #ddd;">
        <label>📎 Прикріпити файл (до 5 МБ)</label>
        <input type="file" name="attachment" class="form-control" accept="image/png, image/jpeg, image/gif, .pdf, .zip, .txt">
    </div>

    <div class="checkbox">
        <label>
            <input type="checkbox" name="is_private" value="1"> <strong>Приватна</strong> (тільки за посиланням, сховається з публічного списку)
        </label>
    </div>

    <div class="checkbox" style="background:#ffeeee; padding:10px; border:1px dashed red;">
        <label class="text-danger">
            <input type="checkbox" name="is_paid" id="is_paid" value="1"> <strong>💲 Платна паста</strong>
            <br><small>Увага: Написання пасти коштує 1 кредит за 10 символів вашого тексту. А також ви зможете встановити ціну за перегляд іншими.</small>
        </label>
        <div id="view_cost_container" style="display:none; margin-top: 10px;">
            <label>Ціна за перегляд (в кредитах):</label>
            <div class="col-xs-12 col-sm-4" style="padding-left:0;">
                <input type="number" class="form-control" name="view_cost" id="view_cost" min="1">
            </div>
        </div>
    </div>

    <div class="form-group" style="background:#eef5ff; padding:10px; border:1px dashed #337ab7; margin-top:10px;">
        <label class="text-primary"><strong>⏰ Час життя пасти</strong></label>
        <select class="form-control" name="expires_in">
            <option value="0">♾️ Вічна (без обмежень)</option>
            <option value="10">🕐 10 хвилин</option>
            <option value="30">🕐 30 хвилин</option>
            <option value="60">🕐 1 година</option>
            <option value="360">🕐 6 годин</option>
            <option value="1440">🕐 24 години</option>
            <option value="10080">🕐 7 днів</option>
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