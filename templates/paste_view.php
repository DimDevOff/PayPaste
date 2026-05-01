<?php if(!$paste): ?>
    <div class="alert alert-danger">
        <h2>Помилка 404!</h2>
        <p>Пасту не знайдено або її вкрали.</p>
    </div>
<?php elseif($paste->isExpired()): ?>
    <div class="alert alert-warning text-center" style="border: 3px dashed var(--accent); padding: 30px; background: var(--bg-secondary);">
        <h2>⏰ Ця паста протермінована!</h2>
        <p>Час життя цієї пасти закінчився <strong><?= $paste->expires_at ?></strong>.</p>
        <p class="text-muted">Вона більше недоступна для перегляду.</p>
        <a href="index.php" class="btn btn-default">← На головну</a>
    </div>
<?php else: ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <?= htmlspecialchars($paste->title ?: 'Без назви') ?>
                <span class="pull-right text-muted" style="font-size:12px;">
                    Створено: <?= $paste->created_at ?>
                    <?php if($paste->expires_at): ?>
                        <br><span style="color: var(--accent);">⏰ Зникне: <?= $paste->expires_at ?></span>
                    <?php endif; ?>
                </span>
            </h3>
        </div>
        <div class="panel-body">

            <?php if(isset($is_locked) && $is_locked): ?>
                <div class="alert alert-warning text-center" style="border: 2px dashed var(--panel-danger-border); padding: 30px; background: var(--bg-secondary);">
                   <h2>Ця паста платна!</h2>
                   <p>Щоб переглянути її, потрібно заплатити <strong><?= $paste->view_cost ?> кредитів</strong>.</p>
                   <form action="view.php" method="POST" style="margin-top:20px;">
                       <?= csrf_field() ?>
                       <input type="hidden" name="action" value="unlock_paste">
                       <input type="hidden" name="paste_id" value="<?= $paste->id ?>">
                       <button class="btn btn-warning btn-lg blink-text" style="font-weight:bold;">Купити доступ за <?= $paste->view_cost ?> КР</button>
                   </form>
                </div>
                </div>
            <?php elseif(isset($requires_quest) && $requires_quest): ?>
                <div class="alert alert-info text-center" id="quest-container" style="border: 2px solid var(--link-color); padding: 20px; background: var(--bg-secondary);">
                    <h3 style="color: var(--link-color); margin-top: 0;">🚀 Рекламний Квест!</h3>
                    <p>Для доступу до цієї безкоштовної пасти, ви маєте пройти невеликий квест.</p>
                    <p>Потрібно переглянути 3 реклами від наших партнерів (по 10 секунд кожна).</p>
                    <div id="quest-status" style="margin: 15px 0;">
                        <strong>Прогрес: <span id="ads-count"><?= $_SESSION['ads_watched'] ?? 0 ?></span> / 3</strong>
                    </div>
                    <button id="start-quest-btn" data-url="<?= ADSTERRA_SMARTLINK_URL ?>" class="btn btn-primary btn-lg blink-text" style="font-weight:bold;">
                        📺 ПЕРЕГЛЯНУТИ РЕКЛАМУ (10 сек)
                    </button>
                    <div id="quest-timer-container" style="display:none; margin-top:15px;">
                        <p>Зачекайте... <strong id="quest-timer">10</strong> секунд</p>
                        <div class="progress" style="height: 10px; margin-bottom: 0; background: var(--input-border);">
                            <div id="quest-progress-bar" class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%; background-color: var(--accent);"></div>
                        </div>
                    </div>
                    <p class="text-muted" style="margin-top:10px;"><small>* Це допомагає нам тримати сервіс безкоштовним!</small></p>
                </div>
            <?php else: ?>
                <?php if($paste->is_paid): ?>
                    <div class="alert alert-success text-center">
                        <p>Ви вже маєте доступ до цієї преміум пасти!</p>
                    </div>
                <?php endif; ?>
                
                <div id="paste-content">

                    <div style="margin-bottom: 10px;">
                        <button id="copy-btn" class="btn btn-primary" style="font-weight: bold; border: 2px outset var(--border-color);">
                            📋 КОПІЮВАТИ
                        </button>
                        <span id="copy-msg" style="margin-left: 10px; color: var(--accent); font-weight: bold; display: none; background: var(--bg-secondary); padding: 2px 5px; border: 1px solid var(--accent);">СКОПІЙОВАНО! ✅</span>
                    </div>
                    <textarea id="paste-textarea" class="form-control" rows="20" readonly style="font-family: monospace; cursor: text;"><?= htmlspecialchars(Paste::stripTags($paste->content)) ?></textarea>

                    <!-- Adsterra: Banner 300×250 (після контенту) -->
                    <div class="text-center" style="margin-top: 20px; overflow: hidden;">
                        <script>
                            atOptions = {
                                'key' : '<?= ADSTERRA_300x250_KEY ?>',
                                'format' : 'iframe',
                                'height' : 250,
                                'width' : 300,
                                'params' : {}
                            };
                        </script>
                        <script src="<?= ADSTERRA_INVOKE_BASE_URL ?>/<?= ADSTERRA_300x250_KEY ?>/invoke.js"></script>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <strong>Теги:</strong>
                        <?php 
                            $tags = $paste->getTags();
                            if ($tags):
                                foreach ($tags as $t):
                        ?>
                            <a href="index.php?tag=<?= urlencode($t) ?>" class="btn btn-xs btn-default" style="margin-right: 5px; background: <?= Paste::getTagColor($t) ?>; color: #fff; border: 1px solid #000; font-family: 'Comic Sans MS', cursive;">#<?= htmlspecialchars($t) ?></a>
                        <?php 
                                endforeach;
                            else:
                        ?>
                            <span class="text-muted">теги відсутні...</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php
                    // Перевірка чи є прикріплений файл
                    $files = glob(__DIR__ . '/../data/uploads/' . $paste->id . '.*');
                    if (!empty($files)):
                        $filePath = $files[0];
                        $fileName = basename($filePath);
                        $fileUrl = 'api/download.php?id=' . $paste->id;
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    ?>
                        <div style="margin-top: 15px; padding: 15px; border: 2px dashed var(--border-color); background: var(--bg-primary);">
                            <h4>📎 Прикріплений файл:</h4>
                            <?php if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <img src="<?= $fileUrl ?>" style="max-width: 100%; height: auto; border: 1px solid var(--border-color);">
                            <?php else: ?>
                                <a href="<?= $fileUrl ?>" class="btn btn-info" download>⬇️ Завантажити <?= htmlspecialchars($ext) ?> файл</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
