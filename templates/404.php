<?php
/**
 * Сторінка 404 — Not Found
 */
$pageTitle = '404 — Сторінку не знайдено';
require __DIR__ . '/header.php';
?>
<div class="container" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div class="text-center" style="max-width: 500px;">
        <h1 style="font-size: 72px; color: var(--accent); margin-bottom: 0;">404</h1>
        <h2 style="color: var(--text-primary); margin-top: 0;">😵 Сторінку не знайдено</h2>
        <p class="text-muted">Такої сторінки не існує, або її видалили.</p>
        <a href="index.php" class="btn btn-default btn-lg" style="margin-top: 20px;">← На головну</a>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
