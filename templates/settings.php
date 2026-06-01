<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/../includes/models/User.php';
require_once __DIR__ . '/../includes/models/Paste.php';
require_once __DIR__ . '/../includes/models/Passkey.php';
$user = User::findById($_SESSION['user_id']);
if (!$user) {
    echo "Користувача не знайдено.";
    exit;
}
$myPastes = Paste::findByUserId($_SESSION['user_id']);
$myPasskeys = Passkey::findByUserId($_SESSION['user_id']);
?>
<div class="row">
    <div class="col-xs-12 col-sm-10 col-sm-offset-1 col-md-6 col-md-offset-3">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold;">[ НАЛАШТУВАННЯ ПРОФІЛЮ ]</h3>
            </div>
            <div class="panel-body">
                <form action="settings.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="nickname">Нікнейм:</label>
                        <input type="text" 
                               name="nickname" 
                               id="nickname" 
                               class="form-control" 
                               value="<?= htmlspecialchars($user->nickname) ?>" 
                               required>
                        <small class="text-muted">Від 1 до 50 символів.</small>
                    </div>

                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" 
                               name="email" 
                               id="email" 
                               class="form-control" 
                               value="<!--email_off--><?= htmlspecialchars($user->email) ?><!--/email_off-->" 
                               required>
                        <small class="text-muted">Увага! При зміні пошти потрібно буде її заново підтвердити.</small>
                    </div>

                    <hr style="border-top: 1px dashed var(--border-color); margin-top: 30px; margin-bottom: 20px;">
                    <h4 style="text-transform: uppercase; font-weight: bold;">Зміна пароля</h4>
                    <p class="text-muted" style="font-size: 0.9em; margin-bottom: 15px;">Залиште поля порожніми, якщо не бажаєте змінювати пароль.</p>

                    <div class="form-group">
                        <label for="current_password">Поточний пароль:</label>
                        <input type="password" 
                               name="current_password" 
                               id="current_password" 
                               class="form-control"
                               autocomplete="current-password">
                        <small class="text-muted">Обов'язково при зміні пароля.</small>
                    </div>

                    <div class="form-group">
                        <label for="password">Новий пароль:</label>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               class="form-control"
                               autocomplete="new-password">
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label for="password_confirm">Підтвердження нового пароля:</label>
                        <input type="password" 
                               name="password_confirm" 
                               id="password_confirm" 
                               class="form-control"
                               autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-warning btn-block blink-text" style="font-family: 'Courier New', Courier, monospace; font-weight: bold; font-size: 1.1em; white-space: normal; height: auto;">✓ ЗБЕРЕГТИ ЗМІНИ ✓</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Секція вибору кольорової теми -->
<div class="row" style="margin-top: 30px;">
    <div class="col-xs-12 col-sm-10 col-sm-offset-1 col-md-6 col-md-offset-3">
        <div class="panel panel-default" style="border: 2px solid var(--border-color);">
            <div class="panel-heading">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold;">[ 🎨 ТЕМА САЙТУ ]</h3>
            </div>
            <div class="panel-body">
                <form action="settings.php" method="POST" id="themeForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_theme">
                    
                    <p class="text-center" style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 20px;">
                        Оберіть тему та натисніть "Зберегти". Попередній перегляд — миттєвий!
                    </p>

                    <div class="row">
                        <!-- Retro (за замовчуванням) -->
                        <div class="col-xs-6 col-sm-4" style="margin-bottom: 15px;">
                            <label class="theme-card <?= ($user->theme ?? 'retro') === 'retro' ? 'theme-card-active' : '' ?>" style="display: block; cursor: pointer; border: 2px solid var(--border-color); padding: 8px; text-align: center; border-radius: 4px;">
                                <input type="radio" name="theme" value="retro" <?= ($user->theme ?? 'retro') === 'retro' ? 'checked' : '' ?> style="display: none;">
                                <div style="background: #dcdcdc; height: 40px; border-radius: 2px; margin-bottom: 5px; position: relative;">
                                    <div style="background: #222; height: 8px; border-radius: 2px 2px 0 0;"></div>
                                    <div style="background: #ffcc00; width: 30%; height: 6px; margin: 4px auto; border-radius: 2px;"></div>
                                </div>
                                <small style="font-weight: bold; color: var(--text-primary); display: block; word-wrap: break-word; white-space: normal;">☠ Retro</small>
                            </label>
                        </div>
                        <!-- Dark -->
                        <div class="col-xs-6 col-sm-4" style="margin-bottom: 15px;">
                            <label class="theme-card <?= ($user->theme ?? 'retro') === 'dark' ? 'theme-card-active' : '' ?>" style="display: block; cursor: pointer; border: 2px solid var(--border-color); padding: 8px; text-align: center; border-radius: 4px;">
                                <input type="radio" name="theme" value="dark" <?= ($user->theme ?? 'retro') === 'dark' ? 'checked' : '' ?> style="display: none;">
                                <div style="background: #1a1a1a; height: 40px; border-radius: 2px; margin-bottom: 5px; position: relative;">
                                    <div style="background: #000; height: 8px; border-radius: 2px 2px 0 0;"></div>
                                    <div style="background: #9b59b6; width: 30%; height: 6px; margin: 4px auto; border-radius: 2px;"></div>
                                </div>
                                <small style="font-weight: bold; color: var(--text-primary); display: block; word-wrap: break-word; white-space: normal;">🌑 Dark</small>
                            </label>
                        </div>

                        <!-- Terminal -->
                        <div class="col-xs-6 col-sm-4" style="margin-bottom: 15px;">
                            <label class="theme-card <?= ($user->theme ?? 'retro') === 'terminal' ? 'theme-card-active' : '' ?>" style="display: block; cursor: pointer; border: 2px solid var(--border-color); padding: 8px; text-align: center; border-radius: 4px;">
                                <input type="radio" name="theme" value="terminal" <?= ($user->theme ?? 'retro') === 'terminal' ? 'checked' : '' ?> style="display: none;">
                                <div style="background: #001100; height: 40px; border-radius: 2px; margin-bottom: 5px; position: relative;">
                                    <div style="background: #003300; height: 8px; border-radius: 2px 2px 0 0;"></div>
                                    <div style="background: #00ff00; width: 30%; height: 6px; margin: 4px auto; border-radius: 2px;"></div>
                                </div>
                                <small style="font-weight: bold; color: var(--text-primary); display: block; word-wrap: break-word; white-space: normal;">💻 Terminal</small>
                            </label>
                        </div>

                        <!-- Light -->
                        <div class="col-xs-6 col-sm-4" style="margin-bottom: 15px;">
                            <label class="theme-card <?= ($user->theme ?? 'retro') === 'light' ? 'theme-card-active' : '' ?>" style="display: block; cursor: pointer; border: 2px solid var(--border-color); padding: 8px; text-align: center; border-radius: 4px;">
                                <input type="radio" name="theme" value="light" <?= ($user->theme ?? 'retro') === 'light' ? 'checked' : '' ?> style="display: none;">
                                <div style="background: #ffffff; height: 40px; border-radius: 2px; margin-bottom: 5px; position: relative;">
                                    <div style="background: #f0f0f0; height: 8px; border-radius: 2px 2px 0 0;"></div>
                                    <div style="background: #0066cc; width: 30%; height: 6px; margin: 4px auto; border-radius: 2px;"></div>
                                </div>
                                <small style="font-weight: bold; color: var(--text-primary); display: block; word-wrap: break-word; white-space: normal;">☀️ Light</small>
                            </label>
                        </div>

                        <!-- GitHub Dark -->
                        <div class="col-xs-6 col-sm-4" style="margin-bottom: 15px;">
                            <label class="theme-card <?= ($user->theme ?? 'retro') === 'github' ? 'theme-card-active' : '' ?>" style="display: block; cursor: pointer; border: 2px solid var(--border-color); padding: 8px; text-align: center; border-radius: 4px;">
                                <input type="radio" name="theme" value="github" <?= ($user->theme ?? 'retro') === 'github' ? 'checked' : '' ?> style="display: none;">
                                <div style="background: #0d1117; height: 40px; border-radius: 2px; margin-bottom: 5px; position: relative;">
                                    <div style="background: #010409; height: 8px; border-radius: 2px 2px 0 0;"></div>
                                    <div style="background: #58a6ff; width: 30%; height: 6px; margin: 4px auto; border-radius: 2px;"></div>
                                </div>
                                <small style="font-weight: bold; color: var(--text-primary); display: block; word-wrap: break-word; white-space: normal;">🐙 GitHub Dark</small>
                            </label>
                        </div>

                        <!-- Retro Green -->
                        <div class="col-xs-6 col-sm-4" style="margin-bottom: 15px;">
                            <label class="theme-card <?= ($user->theme ?? 'retro') === 'retro-green' ? 'theme-card-active' : '' ?>" style="display: block; cursor: pointer; border: 2px solid <?= ($user->theme ?? 'retro') === 'retro-green' ? '#4a8c1c' : 'var(--border-color)' ?>; padding: 8px; text-align: center; border-radius: 4px;">
                                <input type="radio" name="theme" value="retro-green" <?= ($user->theme ?? 'retro') === 'retro-green' ? 'checked' : '' ?> style="display: none;">
                                <div style="background: #acd68e; height: 40px; border-radius: 2px; margin-bottom: 5px; position: relative;">
                                    <div style="background: #2a5a00; height: 8px; border-radius: 2px 2px 0 0;"></div>
                                    <div style="background: #4a8c1c; width: 30%; height: 6px; margin: 4px auto; border-radius: 2px;"></div>
                                </div>
                                <small style="font-weight: bold; color: var(--text-primary); display: block; word-wrap: break-word; white-space: normal;">💚 Retro Green</small>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning btn-block" style="font-family: 'Courier New', Courier, monospace; font-weight: bold; font-size: 1.1em; margin-top: 10px; white-space: normal; height: auto;">🎨 ЗБЕРЕГТИ ТЕМУ 🎨</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Секція керування Passkey -->
<div class="row" style="margin-top: 30px;">
    <div class="col-xs-12 col-sm-10 col-sm-offset-1 col-md-6 col-md-offset-3">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold;">[ 🔑 ПАСКЕЇ (PASSKEYS) — <?= count($myPasskeys) ?> шт. ]</h3>
            </div>
            <div class="panel-body">
                <?php if (count($myPasskeys) < 5): ?>
                    <button type="button" class="btn btn-warning btn-block passkey-btn" id="btn-add-passkey" data-nickname="<?= htmlspecialchars($user->nickname) ?>" style="font-weight:bold; margin-bottom: 20px; white-space: normal; height: auto;">
                        ➕ Додати новий Passkey
                    </button>
                <?php else: ?>
                    <div class="alert alert-warning" style="margin-bottom: 20px;">
                        Максимум 5 passkey. Видаліть існуючий, щоб додати новий.
                    </div>
                <?php endif; ?>

                <?php if (empty($myPasskeys)): ?>
                    <p class="text-center text-muted" style="font-style: italic; padding: 20px 0;">
                        У вас поки що немає жодного passkey...<br>
                        Використовуйте апаратний ключ (YubiKey) або вбудований (Touch ID / Windows Hello)
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" style="margin-bottom: 0;">
                            <thead>
                                <tr style="font-family: Tahoma, sans-serif; text-transform: uppercase; font-size: 0.85em; font-weight: bold;">
                                    <th style="width: 50%;">AAGUID</th>
                                    <th style="width: 25%;" class="hidden-xs">Створено</th>
                                    <th style="width: 25%;">Дії</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myPasskeys as $pk): ?>
                                    <tr>
                                        <td style="vertical-align: middle; font-family: monospace; font-size: 0.85em; word-break: break-all;">
                                            <?= $pk->aaguid ? htmlspecialchars($pk->aaguid) : 'Unknown' ?>
                                        </td>
                                        <td style="text-align: center; vertical-align: middle; font-size: 0.85em; font-family: monospace;" class="hidden-xs">
                                            <?= date('d.m.Y H:i', strtotime($pk->created_at)) ?>
                                        </td>
                                        <td style="text-align: center; vertical-align: middle;">
                                            <button type="button" class="btn btn-xs btn-danger btn-delete-passkey" title="Видалити passkey" data-passkey-id="<?= htmlspecialchars($pk->id) ?>" style="font-family: monospace;">
                                                🗑 Видалити
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div id="passkey-error" class="alert alert-danger" style="display:none; margin-top: 15px;"></div>
                <div id="passkey-success" class="alert alert-success" style="display:none; margin-top: 15px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Секція ПОВ'ЯЗАНІ АКАУНТИ -->
<div class="row" style="margin-top: 30px;">
    <div class="col-xs-12 col-sm-10 col-sm-offset-1 col-md-6 col-md-offset-3">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold;">[ 🔗 ПОВ'ЯЗАНІ АКАУНТИ ]</h3>
            </div>
            <div class="panel-body">
                <!-- GitHub -->
                <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-secondary); border: 1px solid var(--border-color); overflow: hidden;">
                    <div class="pull-right-mobile">
                        <?php if ($user->github_id): ?>
                            <form action="settings.php" method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlink_account">
                                <input type="hidden" name="provider" value="github">
                                <button type="submit" class="btn btn-xs btn-danger">Від'єднати</button>
                            </form>
                        <?php else: ?>
                            <a href="api/oauth.php?provider=github" class="btn btn-xs btn-success">Прив'язати</a>
                        <?php endif; ?>
                    </div>
                    <span style="color: var(--text-primary); font-weight: bold;">GitHub ID:</span> 
                    <span style="color: var(--link-color); font-family: monospace;">
                        <?= $user->github_id ? htmlspecialchars($user->github_id) : '<span style="color: var(--text-muted);">Не привʼязано</span>' ?>
                    </span>
                </div>

                <!-- Telegram -->
                <div style="margin-bottom: 5px; padding: 10px; background: var(--bg-secondary); border: 1px solid var(--border-color); overflow: hidden;">
                    <div class="pull-right-mobile">
                        <?php if ($user->telegram_id): ?>
                            <form action="settings.php" method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlink_account">
                                <input type="hidden" name="provider" value="telegram">
                                <button type="submit" class="btn btn-xs btn-danger">Від'єднати</button>
                            </form>
                        <?php else: ?>
                            <script nonce="<?= csp_nonce() ?>" async src="https://telegram.org/js/telegram-widget.js?22" 
                                    data-telegram-login="PayPasteBot" 
                                    data-size="small" 
                                    data-auth-url="<?= rtrim(APP_URL, '/') ?>/api/oauth.php?provider=telegram" 
                                    data-request-access="write"></script>
                        <?php endif; ?>
                    </div>
                    <span style="color: var(--text-primary); font-weight: bold;">Telegram ID:</span> 
                    <span style="color: var(--link-color); font-family: monospace;">
                        <?= $user->telegram_id ? htmlspecialchars($user->telegram_id) : '<span style="color: var(--text-muted);">Не привʼязано</span>' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Секція керування пастами -->
<div class="row" style="margin-top: 30px;">
    <div class="col-xs-12 col-sm-12 col-md-8 col-md-offset-2">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold;">[ МОЇ ПАСТИ — <?= count($myPastes) ?> шт. ]</h3>
            </div>
            <div class="panel-body">
                <?php if (empty($myPastes)): ?>
                    <p class="text-center" style="color: var(--text-muted); font-style: italic; padding: 30px 0;">
                        У вас поки що немає жодної пасти... 😢<br>
                        <a href="index.php" style="color: var(--accent);">Створити першу пасту!</a>
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" style="border-color: var(--border-color); margin-bottom: 0;">
                            <thead>
                                <tr style="background-color: var(--table-header-bg); color: var(--table-header-text); font-family: Tahoma, sans-serif; text-transform: uppercase; font-size: 0.85em; font-weight: bold;">
                                    <th style="border-color: var(--border-color); width: 30%;">Назва</th>
                                    <th style="border-color: var(--border-color); width: 15%; text-align: center;">Статус</th>
                                    <th style="border-color: var(--border-color); width: 12%; text-align: center;">Ціна</th>
                                    <th style="border-color: var(--border-color); width: 18%; text-align: center;" class="hidden-xs">Створено</th>
                                    <th style="border-color: var(--border-color); width: 25%; text-align: center;">Дії</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myPastes as $paste): ?>
                                    <?php
                                        $isExpired = $paste->isExpired();
                                        $rowStyle = $isExpired ? 'background-color: var(--panel-danger-bg); opacity: 0.7;' : 'background-color: var(--bg-secondary);';
                                    ?>
                                    <tr style="<?= $rowStyle ?>">
                                        <td style="border-color: var(--border-color); vertical-align: middle;">
                                            <?php if ($isExpired): ?>
                                                <span style="color: var(--text-muted); text-decoration: line-through;" title="Паста протермінована">
                                                    <?= htmlspecialchars($paste->title) ?>
                                                </span>
                                                <span style="color: var(--panel-danger-border); font-size: 0.75em; display: block;">☠ EXPIRED</span>
                                            <?php else: ?>
                                                <a href="view.php?id=<?= htmlspecialchars($paste->id) ?>" style="color: var(--link-color); text-decoration: none; font-weight: bold;" title="Переглянути пасту">
                                                    <?= htmlspecialchars($paste->title) ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td style="border-color: var(--border-color); text-align: center; vertical-align: middle;">
                                            <?php if ($isExpired): ?>
                                                <span class="label label-danger">МЕРТВА</span>
                                            <?php elseif ($paste->is_private): ?>
                                                <span class="label label-warning" style="background-color: var(--accent);">🔒 Приватна</span>
                                            <?php elseif ($paste->is_paid): ?>
                                                <span class="label label-success" style="background-color: var(--accent);">💰 Платна</span>
                                            <?php else: ?>
                                                <span class="label label-default">🌐 Публічна</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="border-color: var(--border-color); text-align: center; vertical-align: middle; color: var(--accent); font-weight: bold;">
                                            <?php if ($paste->is_paid && $paste->view_cost > 0): ?>
                                                <?= $paste->view_cost ?> 💰
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="border-color: var(--border-color); text-align: center; vertical-align: middle; color: var(--text-muted); font-size: 0.85em; font-family: monospace;" class="hidden-xs">
                                            <?= date('d.m.Y H:i', strtotime($paste->created_at)) ?>
                                            <?php if ($paste->expires_at): ?>
                                                <br><span style="color: var(--accent); font-size: 0.85em;">
                                                    ⏱ <?= date('d.m.Y H:i', strtotime($paste->expires_at)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="border-color: var(--border-color); text-align: center; vertical-align: middle;">
                                            <?php if (!$isExpired): ?>
                                                <form action="settings.php" method="POST" style="display: block; margin: 2px;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_visibility">
                                                    <input type="hidden" name="paste_id" value="<?= htmlspecialchars($paste->id) ?>">
                                                    <button type="submit" class="btn btn-xs <?= $paste->is_private ? 'btn-info' : 'btn-default' ?> btn-block" title="<?= $paste->is_private ? 'Зробити публічною' : 'Зробити приватною' ?>" style="font-family: monospace;">
                                                        <?= $paste->is_private ? '🔓 Розкрити' : '🔒 Сховати' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form action="settings.php" method="POST" class="form-delete-paste" style="display: block; margin: 2px;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_paste">
                                                <input type="hidden" name="paste_id" value="<?= htmlspecialchars($paste->id) ?>">
                                                <button type="submit" class="btn btn-xs btn-danger btn-block" title="Видалити пасту назавжди" style="font-family: monospace;">
                                                    🗑 Видалити
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Секція API налаштувань -->
<div class="row" style="margin-top: 30px;">
    <div class="col-xs-12 col-sm-10 col-sm-offset-1 col-md-6 col-md-offset-3">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold;">[ 🛰️ API НАЛАШТУВАННЯ ]</h3>
            </div>
            <div class="panel-body">
                <p class="text-muted" style="font-size: 0.9em; margin-bottom: 15px;">
                    Використовуйте цей ключ для автоматизації роботи з пастами через REST API. 
                    <b>Нікому не показуйте цей ключ!</b>
                </p>

                <div class="form-group">
                    <label>Ваш API Ключ:</label>
                    <div class="input-group-mobile">
                        <input type="text" class="form-control" id="apiKeyInput" value="<?= $user->api_key ? htmlspecialchars($user->api_key) : 'Ключ ще не згенеровано' ?>" readonly style="font-family: monospace; background: var(--bg-secondary); color: var(--text-primary); margin-bottom: 5px;">
                        <button class="btn btn-default btn-block" type="button" id="btn-copy-api-key" title="Копіювати">
                            <i class="glyphicon glyphicon-copy"></i> Скопіювати ключ
                        </button>
                    </div>
                </div>

                <form action="settings.php" method="POST" id="form-regen-api-key">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="generate_api_key">
                    <button type="submit" class="btn btn-warning btn-block" style="font-weight: bold; white-space: normal; height: auto;">
                        <?= $user->api_key ? '🔄 Перегенерувати ключ' : '➕ Згенерувати API ключ' ?>
                    </button>
                </form>

                <div style="margin-top: 20px; border-top: 1px dashed var(--border-color); padding-top: 15px;">
                    <h5 style="font-weight: bold; text-transform: uppercase;">Документація API:</h5>
                    <ul style="font-size: 0.85em; color: var(--text-muted); padding-left: 20px;">
                        <li><code>POST /api/auth/token</code> — Отримати JWT за API ключем</li>
                        <li><code>GET /api/pastes/{id}</code> — Перегляд пасти</li>
                        <li><code>POST /api/pastes</code> — Створення пасти</li>
                        <li><code>DELETE /api/pastes/{id}</code> — Видалення пасти</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
// Копіювання API ключа
document.getElementById('btn-copy-api-key').addEventListener('click', function() {
    var copyText = document.getElementById("apiKeyInput");
    if (copyText.value === 'Ключ ще не згенеровано') return;
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    alert("Ключ скопійовано!");
});

// Регенерація API ключа — підтвердження
document.getElementById('form-regen-api-key').addEventListener('submit', function(e) {
    if (!confirm('Ви впевнені? Старий ключ перестане працювати!')) {
        e.preventDefault();
    }
});

// Додавання Passkey
document.getElementById('btn-add-passkey').addEventListener('click', function() {
    var nickname = this.dataset.nickname;
    registerPasskey(nickname);
});

// Видалення Passkey (делегування для динамічних елементів)
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-delete-passkey');
    if (btn) {
        deletePasskey(btn.dataset.passkeyId);
    }
});

// Підтвердження видалення пасти (делегування)
document.addEventListener('submit', function(e) {
    if (e.target.classList.contains('form-delete-paste')) {
        var title = e.target.querySelector('[name="paste_id"]')?.value || 'цю пасту';
        if (!confirm('Точно видалити пасту? Це незворотна дія!')) {
            e.preventDefault();
        }
    }
});

// Валідація: поточний пароль обов'язковий при зміні пароля
(function() {
    var form = document.querySelector('form[action="settings.php"]');
    if (!form) return;
    var currentPw = document.getElementById('current_password');
    var newPw = document.getElementById('password');
    var confirmPw = document.getElementById('password_confirm');

    form.addEventListener('submit', function(e) {
        if (newPw && newPw.value.length > 0) {
            if (!currentPw || currentPw.value.length === 0) {
                e.preventDefault();
                alert('Введіть поточний пароль для зміни пароля!');
                if (currentPw) currentPw.focus();
                return;
            }
        }
    });
})();

// Підтвердження видалення акаунта (тільки на submit форми, без дублювання)
document.getElementById('btn-confirm-delete-account').closest('form').addEventListener('submit', function(e) {
    if (!confirm('ВИ ВПЕВНЕНІ? Ця дія НЕЗВОРОТНА!')) {
        e.preventDefault();
    }
});

// Підтвердження видалення акаунта через Passkey
document.getElementById('btn-confirm-delete-passkey').addEventListener('click', function() {
    confirmDeleteAccountPasskey();
});
</script>

<!-- Секція НЕБЕЗПЕЧНА ЗОНА (Видалення акаунта) -->
<div class="row" style="margin-top: 50px; margin-bottom: 50px;">
    <div class="col-xs-12 col-sm-10 col-sm-offset-1 col-md-6 col-md-offset-3">
        <div class="panel panel-danger" style="border: 2px solid var(--panel-danger-border);">
            <div class="panel-heading">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold;">[ 💀 НЕБЕЗПЕЧНА ЗОНА ]</h3>
            </div>
            <div class="panel-body text-center">
                <p style="color: var(--panel-danger-border); font-weight: bold; text-transform: uppercase; margin-bottom: 20px;">Видалення акаунта призведе до повної втрати всіх ваших паст та кредитів!</p>
                
                <button type="button" class="btn btn-danger btn-block" data-toggle="collapse" data-target="#deleteForm" style="font-weight: bold; white-space: normal; height: auto;">
                    Я ХОЧУ ВИДАЛИТИ СВІЙ АКАУНТ
                </button>

                <div id="deleteForm" class="collapse" style="margin-top: 20px; padding: 15px; background: var(--bg-primary); border: 1px dashed var(--panel-danger-border);">
                    <form action="settings.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_account">
                        
                        <?php $confirmed = isset($_SESSION['passkey_confirmed_delete']) || isset($_SESSION['oauth_confirmed_delete']); ?>
                        
                        <div class="form-group">
                            <label style="color: var(--text-primary);">
                                <?php if ($confirmed): ?>
                                    Пароль більше не потрібен (особу підтверджено):
                                <?php else: ?>
                                    Введіть ваш пароль для підтвердження:
                                <?php endif; ?>
                            </label>
                            <input type="password" name="password" class="form-control" 
                                   style="border-color: var(--border-color);" 
                                   <?= $confirmed ? '' : 'required' ?>
                                   placeholder="<?= $confirmed ? 'Можна залишити порожнім' : '*****' ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-lg btn-danger btn-block" id="btn-confirm-delete-account" style="white-space: normal; height: auto;">
                            <?php if ($confirmed): ?>
                                💀 ОСТАТОЧНО ВИДАЛИТИ АКАУНТ 💀
                            <?php else: ?>
                                ПІДТВЕРДИТИ ПАРОЛЕМ ☠
                            <?php endif; ?>
                        </button>
                    </form>

                    <div style="margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 15px;">
                        <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 10px;">Швидке підтвердження особи:</p>
                        
                        <?php if (isset($_SESSION['passkey_confirmed_delete']) || isset($_SESSION['oauth_confirmed_delete'])): ?>
                            <div class="alert alert-success" style="background: var(--bg-secondary); color: var(--accent); border: 1px solid var(--accent); padding: 10px; margin-bottom: 15px;">
                                <i class="glyphicon glyphicon-ok"></i> <b>ОСОБУ ПІДТВЕРДЖЕНО!</b><br>
                                <small>Тепер ви можете видалити акаунт кнопкою вище (пароль не потрібен).</small>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <?php if (count($myPasskeys) > 0): ?>
                                <div class="col-xs-12" style="margin-bottom: 10px;">
                                    <button type="button" class="btn btn-info btn-block passkey-btn" id="btn-confirm-delete-passkey" style="font-weight: bold; white-space: normal; height: auto;">
                                        ПІДТВЕРДИТИ ЧЕРЕЗ PASSKEY 🔑
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php if ($user->github_id): ?>
                                <div class="col-xs-12" style="margin-bottom: 10px;">
                                    <a href="api/oauth.php?provider=github&confirm_delete_oauth=1" class="btn btn-default btn-block" style="background: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color); font-weight: bold; white-space: normal; height: auto;">
                                        ПІДТВЕРДИТИ ЧЕРЕЗ GITHUB 🐙
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($user->telegram_id): ?>
                                <div class="col-xs-12" style="margin-bottom: 10px;">
                                    <div class="text-center" style="background: var(--bg-secondary); padding: 10px; border: 1px solid var(--border-color);">
                                        <p style="color: var(--text-muted); margin-bottom: 5px;"><small>ПІДТВЕРДИТИ ЧЕРЕЗ TELEGRAM:</small></p>
                                        <script nonce="<?= csp_nonce() ?>" async src="https://telegram.org/js/telegram-widget.js?22" 
                                                data-telegram-login="PayPasteBot" 
                                                data-size="large" 
                                                data-auth-url="<?= rtrim(APP_URL, '/') ?>/api/oauth.php?provider=telegram&confirm_delete_oauth=1" 
                                                data-request-access="write"></script>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

