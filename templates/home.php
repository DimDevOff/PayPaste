<div class="row">
    <div class="col-md-12">
        <h2 style="border-bottom: 2px solid #ccc; padding-bottom: 5px;">Останні пасти</h2>
        <div class="list-group">
            <?php 
               require_once __DIR__ . '/../includes/models/Paste.php';
               $pastes = Paste::findAllPublic();
               foreach(array_slice($pastes, 0, 20) as $p):
            ?>
            <a href="view.php?id=<?= $p->id ?>" class="list-group-item">
                <h4 class="list-group-item-heading" style="word-wrap:break-word;"><?= htmlspecialchars($p->title ?: 'Без назви') ?></h4>
                <p class="list-group-item-text text-muted" style="font-size:11px;">
                    <?= $p->created_at ?>
                    <?php if($p->is_paid): ?> <span class="label label-warning">Paid!</span> <?php endif; ?>
                </p>
            </a>
            <?php endforeach; ?>
            <?php if(empty($pastes)): ?>
                <div class="list-group-item text-muted">Немає публічних паст...</div>
            <?php endif; ?>
        </div>
    </div>
</div>