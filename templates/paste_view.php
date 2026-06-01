<?php if(!$paste): ?>
    <div class="alert alert-danger">
        <h2>Помилка 404!</h2>
        <p>Пасту не знайдено або її вкрали.</p>
    </div>
<?php elseif($paste->isExpired()): ?>
    <div class="alert alert-warning text-center" style="border: 3px dashed var(--accent); padding: 30px; background: var(--bg-secondary);">
        <h2>⏰ Ця паста протермінована!</h2>
        <p>Час життя цієї пасти закінчився <strong><?= htmlspecialchars($paste->expires_at) ?></strong>.</p>
        <p class="text-muted">Вона більше недоступна для перегляду.</p>
        <a href="index.php" class="btn btn-default">← На головну</a>
    </div>
<?php else: ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css" integrity="sha512-57MDmcccJXYtNnH+ZiBwzC4jb2rvgVCEokYN+L/nLlmO8rfYT/gIpW2A569iJ/3b+0UEasghjuZH/ma3wIs/EQ==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script nonce="<?= csp_nonce() ?>" src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js" integrity="sha512-D9gUyxqja7hBtkWpPWGt9wfbfaMGVt9gnyCvYa+jojwwPHLCzUm5i8rpk7vD7wNee9bA35eYIjobYPaQuKS1MQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script nonce="<?= csp_nonce() ?>">hljs.highlightAll();</script>
    
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <?= htmlspecialchars($paste->title ?: 'Без назви') ?>
                <span class="pull-right text-muted" style="font-size:12px;">
                    Створено: <?= htmlspecialchars($paste->created_at) ?>
                    <?php if($paste->expires_at): ?>
                        <br><span style="color: var(--accent);">⏰ Зникне: <?= htmlspecialchars($paste->expires_at) ?></span>
                    <?php endif; ?>
                </span>
            </h3>
        </div>
        <div class="panel-body">
            <?php if($paste->is_pending_rewrite): ?>
                <div class="alert alert-info text-center" style="border: 2px solid var(--link-color); padding: 40px; background: var(--bg-secondary);">
                    <h2 class="blink-text" style="color: var(--accent);">🛠️ ШІ ПРАЦЮЄ...</h2>
                    <p style="font-size: 1.2em;">Ця паста зараз проходить автоматичне перефразування моделлю ШІ.</p>
                    <p>Текст ще не готовий. Будь ласка, <strong>зайдіть пізніше</strong> (через 1-2 хвилини).</p>
                    <div class="progress" style="height: 20px; margin-top: 20px;">
                        <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%; background-color: var(--link-color);"></div>
                    </div>
                    <hr>
                    <p class="text-muted"><small>Ваші налаштування приватності та ціни будуть застосовані автоматично після завершення.</small></p>
                </div>
            <?php elseif(isset($paste->moderation_status) && $paste->moderation_status === 'pending'): ?>
                <div class="alert alert-warning text-center" style="border: 2px dashed var(--accent); padding: 40px; background: var(--bg-secondary);">
                    <h2 class="blink-text" style="color: var(--link-color);">🔍 МОДЕРАЦІЯ...</h2>
                    <p style="font-size: 1.2em;">Ця паста проходить автоматичну перевірку модерації.</p>
                    <p>Вона стане доступною для всіх після підтвердження. <strong>Зайдіть пізніше</strong> (через 1-2 хвилини).</p>
                    <div class="progress" style="height: 20px; margin-top: 20px;">
                        <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%; background-color: var(--accent);"></div>
                    </div>
                    <hr>
                    <p class="text-muted"><small>Якщо паста не пройде модерацію, ви зможете відредагувати текст або скористатися AI-переписуванням.</small></p>
                </div>
            <?php elseif(isset($paste->moderation_status) && $paste->moderation_status === 'rejected'): ?>
                <div class="alert alert-danger text-center" style="border: 2px solid var(--panel-danger-border); padding: 30px; background: var(--bg-secondary);">
                    <h3 style="color: var(--danger);">❌ МОДЕРАЦІЯ ВІДХИЛЕНА</h3>
                    <p style="font-size: 1.1em;">Ваша паста не пройшла автоматичну перевірку модерації.</p>
                    <?php
                    $modResult = json_decode($paste->moderation_result ?? '[]', true);
                    if (!empty($modResult) && isset($modResult['reason'])):
                        // Формат moderation_result від Worker: масив категорій
                    ?>
                        <p>Причини: <strong><?= htmlspecialchars(implode(', ', (array)$modResult)) ?></strong></p>
                    <?php elseif (!empty($modResult) && is_array($modResult)): ?>
                        <p>Причини: <strong><?= htmlspecialchars(implode(', ', $modResult)) ?></strong></p>
                    <?php endif; ?>
                    <hr>
                    <p>Ви можете <a href="create.php" style="color: var(--link-color); font-weight: bold;">створити нову пасту</a> з відредагованим текстом або попросити AI перефразувати її.</p>
                </div>
            <?php elseif(isset($paste->moderation_status) && $paste->moderation_status === 'moderation_failed' && empty($is_admin) && empty($is_author)): ?>
                <div class="alert alert-warning text-center" style="border: 3px dashed #c0392b; padding: 30px; background: var(--bg-secondary);">
                    <h3 style="color: #e74c3c;">⚠️ МОДЕРАЦІЯ НЕ ЗАВЕРШЕНА</h3>
                    <p style="font-size: 1.1em;">Зовнішній сервіс модерації був недоступний, тому перевірка не завершилася.</p>
                    <p>Ваша паста <strong>не буде опублікована автоматично</strong> і очікує ручного розгляду.</p>
                    <?php
                    $modResult = json_decode($paste->moderation_result ?? '[]', true);
                    if (!empty($modResult) && isset($modResult['detail'])):
                    ?>
                        <p class="text-muted"><small>Деталі: <?= htmlspecialchars($modResult['detail']) ?></small></p>
                    <?php endif; ?>
                    <hr>
                    <p>Зачекайте на ручну перевірку адміністратором або <a href="create.php" style="color: var(--link-color); font-weight: bold;">створіть нову пасту</a>.</p>
                </div>
            <?php elseif(isset($is_locked) && $is_locked): ?>
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
                    <p>Ця паста платна: <strong><?= (int)$paste->view_cost ?> кредитів</strong>.</p>
                    <form action="view.php" method="POST" style="margin: 15px 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="unlock_paste">
                        <input type="hidden" name="paste_id" value="<?= htmlspecialchars($paste->id) ?>">
                        <button class="btn btn-warning btn-lg blink-text" style="font-weight:bold;">Купити доступ за <?= (int)$paste->view_cost ?> КР</button>
                    </form>
                    <hr>
                    <p>Або пройдіть рекламний квест: 3 підтверджені перегляди по 10 секунд.</p>
                    <div id="quest-status" style="margin: 15px 0;">
                        <strong>Прогрес: <span id="ads-count"><?= (int)($ad_quest_progress ?? 0) ?></span> / 3</strong>
                    </div>
                    <a href="<?= htmlspecialchars(ADSTERRA_SMARTLINK_URL) ?>"
                       target="_blank"
                       id="start-quest-btn"
                       class="btn btn-primary btn-lg blink-text"
                       data-paste-id="<?= htmlspecialchars($paste->id) ?>"
                       data-ad-token="<?= htmlspecialchars($ad_quest_token ?? '') ?>"
                       data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                       style="font-weight:bold; display: inline-block; text-decoration: none;">
                        📺 ПЕРЕГЛЯНУТИ РЕКЛАМУ (10 сек)
                    </a>
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
                    
                    <div class="code-container" style="position: relative; border: 2px solid var(--border-color); background: #0d1117; margin-bottom: 20px; box-shadow: 5px 5px 0px rgba(0,0,0,0.2);">
                        <div style="background: #161b22; padding: 5px 15px; border-bottom: 1px solid #30363d; display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #8b949e; font-size: 11px; font-family: monospace; text-transform: uppercase;"><?= htmlspecialchars($paste->language ?: 'plaintext') ?></span>
                            <div style="display: flex; gap: 5px;">
                                <div style="width: 10px; height: 10px; border-radius: 50%; background: #ff5f56;"></div>
                                <div style="width: 10px; height: 10px; border-radius: 50%; background: #ffbd2e;"></div>
                                <div style="width: 10px; height: 10px; border-radius: 50%; background: #27c93f;"></div>
                            </div>
                        </div>
                        <pre style="margin: 0; border: none; border-radius: 0; background: transparent; padding: 0; overflow-x: auto;"><code class="hljs language-<?= htmlspecialchars($paste->language ?: 'plaintext') ?>" style="padding: 15px; display: block; background: transparent !important; color: #c9d1d9 !important;"><?= htmlspecialchars($paste->content) ?></code></pre>
                        <textarea id="paste-textarea" style="display:none;"><?= htmlspecialchars($paste->content) ?></textarea>
                    </div>

                    <!-- Adsterra: Banner 300×250 (після контенту) -->
                    <?php if (!isset($hide_ads) || !$hide_ads): ?>
                    <div class="text-center" style="margin-top: 20px; overflow: hidden;">
                        <script nonce="<?= csp_nonce() ?>">
                            atOptions = {
                                'key' : '<?= ADSTERRA_300x250_KEY ?>',
                                'format' : 'iframe',
                                'height' : 250,
                                'width' : 300,
                                'params' : {}
                            };
                        </script>
                        <script nonce="<?= csp_nonce() ?>" src="<?= ADSTERRA_INVOKE_BASE_URL ?>/<?= ADSTERRA_300x250_KEY ?>/invoke.js" crossorigin="anonymous"></script>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 15px;">
                        <strong>Теги:</strong>
                        <?php 
                            $tags = $paste->getTags();
                            if ($tags):
                                foreach ($tags as $t):
                        ?>
                            <a href="index.php?tag=<?= urlencode($t) ?>" class="btn btn-xs btn-default" style="margin-right: 5px; background: <?= htmlspecialchars(Paste::getTagColor($t)) ?>; color: #fff; border: 1px solid #000; font-family: 'Comic Sans MS', cursive;">#<?= htmlspecialchars($t) ?></a>
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
                                <img src="<?= htmlspecialchars($fileUrl) ?>" style="max-width: 100%; height: auto; border: 1px solid var(--border-color);">
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($fileUrl) ?>" class="btn btn-info" download>⬇️ Завантажити <?= htmlspecialchars($ext) ?> файл</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
