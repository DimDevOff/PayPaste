<div class="row">
    <div class="col-md-9">
        <h2 style="border-bottom: 2px solid var(--border-color); padding-bottom: 5px;">
            <?php if (isset($_GET['tag'])): ?>
                Пасти з тегом: <span style="color: var(--accent);">#<?= htmlspecialchars($_GET['tag']) ?></span>
                <a href="index.php" class="btn btn-xs btn-danger">скинути</a>
            <?php else: ?>
                Останні пасти
            <?php endif; ?>
        </h2>
        
        <div style="margin-bottom: 15px;">
            <div class="btn-group">
                <a href="?category=all<?= $tag ? '&tag='.urlencode($tag) : '' ?>" class="btn btn-default <?= $cat === 'all' ? 'active' : '' ?>">Всі</a>
                <a href="?category=paid<?= $tag ? '&tag='.urlencode($tag) : '' ?>" class="btn btn-default <?= $cat === 'paid' ? 'active' : '' ?>">Платні</a>
                <a href="?category=free<?= $tag ? '&tag='.urlencode($tag) : '' ?>" class="btn btn-default <?= $cat === 'free' ? 'active' : '' ?>">Безплатні</a>
                <a href="?category=user<?= $tag ? '&tag='.urlencode($tag) : '' ?>" class="btn btn-default <?= $cat === 'user' ? 'active' : '' ?>">Користувацькі</a>
                <a href="?category=anonymous<?= $tag ? '&tag='.urlencode($tag) : '' ?>" class="btn btn-default <?= $cat === 'anonymous' ? 'active' : '' ?>">Анонімні</a>
            </div>
        </div>

        <div class="list-group">
            <?php 
               foreach($pastes as $p):
            ?>
            <div class="list-group-item">
                <a href="view.php?id=<?= $p->id ?>" style="text-decoration:none; color: var(--text-primary); display:block;">
                    <h4 class="list-group-item-heading" style="word-wrap:break-word; margin-bottom: 2px;"><?= htmlspecialchars($p->title ?: 'Без назви') ?></h4>
                    <p class="list-group-item-text text-muted" style="font-size:11px; margin-bottom: 5px;">
                        <?= htmlspecialchars($p->created_at) ?>
                        <?php if($p->is_paid): ?>
                            <span class="label label-warning" style="font-size:9px;">
                                <?= (int)($p->view_cost ?? 0) ?> КР
                            </span>
                        <?php endif; ?>
                    </p>
                </a>
                
                <div class="paste-tags-list" style="margin-top: 5px;">
                    <?php
                        $pt_info = $paste_tags[$p->id] ?? ['visible' => [], 'hidden' => [], 'has_more' => false];
                        $visible_tags = $pt_info['visible'];
                        $hidden_tags = $pt_info['hidden'];
                        $has_more = $pt_info['has_more'];

                        foreach($visible_tags as $vt): 
                    ?>
                        <a href="index.php?tag=<?= urlencode($vt) ?>" class="btn btn-xs" style="background: <?= htmlspecialchars(Paste::getTagColor($vt)) ?>; color: #fff; padding: 0 4px; font-size: 10px; margin-bottom: 2px;">#<?= htmlspecialchars($vt) ?></a>
                    <?php endforeach; ?>

                    <?php if($has_more): ?>
                        <button class="btn btn-xs btn-link toggle-tags" data-target=".hidden-tags-<?= htmlspecialchars($p->id, ENT_QUOTES, 'UTF-8') ?>" style="padding:0; color: var(--text-primary); text-decoration:none; vertical-align: middle;">
                            <span class="glyphicon glyphicon-chevron-down" style="font-size: 10px;"></span>
                        </button>
                        <div class="hidden-tags-<?= htmlspecialchars($p->id, ENT_QUOTES, 'UTF-8') ?>" style="display:none; margin-top: 5px;">
                            <?php foreach($hidden_tags as $ht): ?>
                                <a href="index.php?tag=<?= urlencode($ht) ?>" class="btn btn-xs" style="background: <?= htmlspecialchars(Paste::getTagColor($ht)) ?>; color: #fff; padding: 0 4px; font-size: 10px; margin-bottom: 2px;">#<?= htmlspecialchars($ht) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($pastes)): ?>
                <div class="list-group-item text-muted">Немає паст за цими критеріями...</div>
            <?php endif; ?>
        </div>

        <script nonce="<?= csp_nonce() ?>">
        $(document).ready(function() {
            $('.toggle-tags').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $(target).toggle();
                $(this).find('span').toggleClass('glyphicon-chevron-down glyphicon-chevron-up');
            });
        });
        </script>
    </div>
    
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading" style="font-weight: bold; font-family: 'Comic Sans MS', cursive;">🔥 Популярні теги</div>
            <div class="panel-body">
                <?php 
                    if ($popularTags):
                        foreach ($popularTags as $t):
                ?>
                    <a href="index.php?tag=<?= urlencode($t['tag']) ?>">
                        <span class="badge" style="background: <?= htmlspecialchars(Paste::getTagColor($t['tag'])) ?>; margin-bottom: 5px;">#<?= htmlspecialchars($t['tag']) ?> (<?= $t['count'] ?>)</span>
                    </a>
                <?php 
                        endforeach;
                    else:
                ?>
                    <span class="text-muted">Тегів ще немає...</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Adsterra: Banner 160×300 (бокова панель) -->
    <div class="text-center">
        <div class="panel panel-default" style="overflow: hidden; display: inline-block; width: 172px;">
            <div class="panel-heading" style="font-weight: bold; font-family: 'Comic Sans MS', cursive; font-size: 11px; padding: 5px;">💰 Реклама</div>
            <div class="panel-body text-center" style="padding: 5px;">
                <script nonce="<?= csp_nonce() ?>">
                    atOptions = {
                        'key' : '<?= ADSTERRA_160x300_KEY ?>',
                        'format' : 'iframe',
                        'height' : 300,
                        'width' : 160,
                        'params' : {}
                    };
                </script>
                <script nonce="<?= csp_nonce() ?>" src="<?= ADSTERRA_INVOKE_BASE_URL ?>/<?= ADSTERRA_160x300_KEY ?>/invoke.js" crossorigin="anonymous"></script>
            </div>
        </div>
    </div>
</div>
