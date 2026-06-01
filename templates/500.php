<?php
/**
 * Сторінка 500 — Internal Server Error
 */
$pageTitle = '500 — Помилка сервера';
http_response_code(500);
require __DIR__ . '/header.php';
?>
<div class="container" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div class="text-center" style="max-width: 500px;">
        <h1 style="font-size: 72px; color: var(--danger); margin-bottom: 0;">500</h1>
        <h2 style="color: var(--text-primary); margin-top: 0;">💥 Внутрішня помилка сервера</h2>
        <p class="text-muted">Щось пішло не так. Ми вже знаємо про проблему і працюємо над її виправленням.</p>
        <a href="index.php" class="btn btn-default btn-lg" style="margin-top: 20px;">← На головну</a>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
