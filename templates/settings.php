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
    <div class="col-md-6 col-md-offset-3">
        <div class="panel panel-default" style="border: 2px solid #555;">
            <div class="panel-heading" style="background-color: #333; border-color: #333; color: #fff;">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold; letter-spacing: 1px;">[ НАЛАШТУВАННЯ ПРОФІЛЮ ]</h3>
            </div>
            <div class="panel-body" style="background: #fdfdfd;">
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
                        <label>Email:</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user->email) ?>" disabled>
                        <small class="text-muted">Email змінити неможливо.</small>
                    </div>

                    <hr style="border-top: 1px dashed #ccc; margin-top: 30px; margin-bottom: 20px;">
                    <h4 style="color: #333; text-transform: uppercase; font-weight: bold;">Зміна пароля</h4>
                    <p class="text-muted" style="font-size: 0.9em; margin-bottom: 15px;">Залиште поля порожніми, якщо облом вигадувати новий пароль.</p>

                    <div class="form-group">
                        <label for="password">Новий пароль:</label>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               class="form-control">
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label for="password_confirm">Підтвердження нового пароля:</label>
                        <input type="password" 
                               name="password_confirm" 
                               id="password_confirm" 
                               class="form-control">
                    </div>

                    <button type="submit" class="btn btn-warning btn-block blink-text" style="font-family: 'Courier New', Courier, monospace; font-weight: bold; font-size: 1.2em; border: 2px solid #ffcc00; color: #000;">✓ ЗБЕРЕГТИ ЗМІНИ ✓</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Секція керування Passkey -->
<div class="row" style="margin-top: 30px;">
    <div class="col-md-6 col-md-offset-3">
        <div class="panel panel-default" style="border: 2px solid #555;">
            <div class="panel-heading" style="background-color: #333; border-color: #333; color: #fff;">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold; letter-spacing: 1px;">[ 🔑 PASSKEY — <?= count($myPasskeys) ?> шт. ]</h3>
            </div>
            <div class="panel-body" style="background: #fdfdfd;">
                <?php if (count($myPasskeys) < 5): ?>
                    <button type="button" class="btn btn-warning btn-block passkey-btn" style="font-weight:bold; border: 1px dashed #d58512; margin-bottom: 20px;" onclick="registerPasskey('<?= htmlspecialchars($user->nickname) ?>')">
                        ➕ Додати новий Passkey
                    </button>
                <?php else: ?>
                    <div class="alert alert-warning" style="background-color: #332200; border-color: #553300; color: #ffaa00; margin-bottom: 20px;">
                        Максимум 5 passkey. Видаліть існуючий, щоб додати новий.
                    </div>
                <?php endif; ?>

                <?php if (empty($myPasskeys)): ?>
                    <p class="text-center" style="color: #777; font-style: italic; padding: 20px 0;">
                        У вас поки що немає жодного passkey...<br>
                        Використовуйте апаратний ключ (YubiKey) або вбудований (Touch ID / Windows Hello)
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" style="border-color: #444; margin-bottom: 0;">
                            <thead>
                                <tr style="background-color: #eee; color: #333; font-family: Tahoma, sans-serif; text-transform: uppercase; font-size: 0.85em; font-weight: bold;">
                                    <th style="border-color: #ccc; width: 50%;">AAGUID</th>
                                    <th style="border-color: #ccc; width: 25%;">Створено</th>
                                    <th style="border-color: #ccc; width: 25%;">Дії</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myPasskeys as $pk): ?>
                                    <tr style="background-color: #fff;">
                                        <td style="border-color: #444; vertical-align: middle; color: #aaa; font-family: monospace; font-size: 0.85em;">
                                            <?= $pk->aaguid ? htmlspecialchars($pk->aaguid) : 'Unknown' ?>
                                        </td>
                                        <td style="border-color: #444; text-align: center; vertical-align: middle; color: #888; font-size: 0.85em; font-family: monospace;">
                                            <?= date('d.m.Y H:i', strtotime($pk->created_at)) ?>
                                        </td>
                                        <td style="border-color: #444; text-align: center; vertical-align: middle;">
                                            <button type="button" class="btn btn-xs btn-danger" title="Видалити passkey" style="font-family: monospace;" onclick="deletePasskey('<?= htmlspecialchars($pk->id) ?>')">
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
    <div class="col-md-6 col-md-offset-3">
        <div class="panel panel-default" style="border: 2px solid #555;">
            <div class="panel-heading" style="background-color: #333; border-color: #333; color: #fff;">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold; letter-spacing: 1px;">[ 🔗 ПОВ'ЯЗАНІ АКАУНТИ ]</h3>
            </div>
            <div class="panel-body" style="background: #fdfdfd;">
                <!-- GitHub -->
                <div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                    <div class="pull-right">
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
                    <span style="color: #333; font-weight: bold;">GitHub ID:</span> 
                    <span style="color: #337ab7; font-family: monospace;">
                        <?= $user->github_id ? htmlspecialchars($user->github_id) : '<span style="color:#999;">Не привʼязано</span>' ?>
                    </span>
                </div>

                <!-- Telegram -->
                <div style="margin-bottom: 5px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                    <div class="pull-right">
                        <?php if ($user->telegram_id): ?>
                            <form action="settings.php" method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlink_account">
                                <input type="hidden" name="provider" value="telegram">
                                <button type="submit" class="btn btn-xs btn-danger">Від'єднати</button>
                            </form>
                        <?php else: ?>
                            <script async src="https://telegram.org/js/telegram-widget.js?22" 
                                    data-telegram-login="PayPasteBot" 
                                    data-size="small" 
                                    data-auth-url="<?= rtrim(APP_URL, '/') ?>/api/oauth.php?provider=telegram" 
                                    data-request-access="write"></script>
                        <?php endif; ?>
                    </div>
                    <span style="color: #333; font-weight: bold;">Telegram ID:</span> 
                    <span style="color: #337ab7; font-family: monospace;">
                        <?= $user->telegram_id ? htmlspecialchars($user->telegram_id) : '<span style="color:#999;">Не привʼязано</span>' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Секція керування пастами -->
<div class="row" style="margin-top: 30px;">
    <div class="col-md-8 col-md-offset-2">
        <div class="panel panel-default" style="border: 2px solid #555;">
            <div class="panel-heading" style="background-color: #333; border-color: #333; color: #fff;">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold; letter-spacing: 1px;">[ МОЇ ПАСТИ — <?= count($myPastes) ?> шт. ]</h3>
            </div>
            <div class="panel-body" style="background: #fdfdfd;">
                <?php if (empty($myPastes)): ?>
                    <p class="text-center" style="color: #777; font-style: italic; padding: 30px 0;">
                        У вас поки що немає жодної пасти... 😢<br>
                        <a href="index.php" style="color: #ffcc00;">Створити першу пасту!</a>
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" style="border-color: #444; margin-bottom: 0;">
                            <thead>
                                <tr style="background-color: #eee; color: #333; font-family: Tahoma, sans-serif; text-transform: uppercase; font-size: 0.85em; font-weight: bold;">
                                    <th style="border-color: #ccc; width: 30%;">Назва</th>
                                    <th style="border-color: #ccc; width: 15%; text-align: center;">Статус</th>
                                    <th style="border-color: #ccc; width: 12%; text-align: center;">Ціна</th>
                                    <th style="border-color: #ccc; width: 18%; text-align: center;">Створено</th>
                                    <th style="border-color: #ccc; width: 25%; text-align: center;">Дії</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myPastes as $paste): ?>
                                    <?php
                                        $isExpired = $paste->isExpired();
                                        $rowStyle = $isExpired ? 'background-color: #f9f2f2; opacity: 0.7;' : 'background-color: #fff;';
                                    ?>
                                    <tr style="<?= $rowStyle ?>">
                                        <!-- Назва пасти -->
                                        <td style="border-color: #ccc; vertical-align: middle;">
                                            <?php if ($isExpired): ?>
                                                <span style="color: #999; text-decoration: line-through;" title="Паста протермінована">
                                                    <?= htmlspecialchars($paste->title) ?>
                                                </span>
                                                <span style="color: #ff4444; font-size: 0.75em; display: block;">☠ EXPIRED</span>
                                            <?php else: ?>
                                                <a href="view.php?id=<?= htmlspecialchars($paste->id) ?>" style="color: #337ab7; text-decoration: none; font-weight: bold;" title="Переглянути пасту">
                                                    <?= htmlspecialchars($paste->title) ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Статус -->
                                        <td style="border-color: #444; text-align: center; vertical-align: middle;">
                                            <?php if ($isExpired): ?>
                                                <span class="label" style="background-color: #660000; color: #ff6666;">МЕРТВА</span>
                                            <?php elseif ($paste->is_private): ?>
                                                <span class="label" style="background-color: #553300; color: #ffaa00;">🔒 Приватна</span>
                                            <?php elseif ($paste->is_paid): ?>
                                                <span class="label" style="background-color: #004400; color: #44ff44;">💰 Платна</span>
                                            <?php else: ?>
                                                <span class="label" style="background-color: #333; color: #aaa;">🌐 Публічна</span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Ціна -->
                                        <td style="border-color: #ccc; text-align: center; vertical-align: middle; color: #8a6d3b; font-weight: bold;">
                                            <?php if ($paste->is_paid && $paste->view_cost > 0): ?>
                                                <?= $paste->view_cost ?> 💰
                                            <?php else: ?>
                                                <span style="color: #ccc;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Дата -->
                                        <td style="border-color: #444; text-align: center; vertical-align: middle; color: #888; font-size: 0.85em; font-family: monospace;">
                                            <?= date('d.m.Y H:i', strtotime($paste->created_at)) ?>
                                            <?php if ($paste->expires_at): ?>
                                                <br><span style="color: <?= $isExpired ? '#ff4444' : '#ffaa00' ?>; font-size: 0.85em;">
                                                    ⏱ <?= date('d.m.Y H:i', strtotime($paste->expires_at)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Дії -->
                                        <td style="border-color: #444; text-align: center; vertical-align: middle;">
                                            <?php if (!$isExpired): ?>
                                                <!-- Перемикання приватності -->
                                                <form action="settings.php" method="POST" style="display: inline-block; margin: 2px;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_visibility">
                                                    <input type="hidden" name="paste_id" value="<?= htmlspecialchars($paste->id) ?>">
                                                    <button type="submit" class="btn btn-xs <?= $paste->is_private ? 'btn-info' : 'btn-default' ?>" title="<?= $paste->is_private ? 'Зробити публічною' : 'Зробити приватною' ?>" style="font-family: monospace;">
                                                        <?= $paste->is_private ? '🔓 Розкрити' : '🔒 Сховати' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <!-- Видалення -->
                                            <form action="settings.php" method="POST" style="display: inline-block; margin: 2px;" onsubmit="return confirm('Точно видалити пасту &quot;<?= htmlspecialchars($paste->title) ?>&quot;? Це незворотна дія!');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_paste">
                                                <input type="hidden" name="paste_id" value="<?= htmlspecialchars($paste->id) ?>">
                                                <button type="submit" class="btn btn-xs btn-danger" title="Видалити пасту назавжди" style="font-family: monospace;">
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

<!-- Секція НЕБЕЗПЕЧНА ЗОНА (Видалення акаунта) -->
<div class="row" style="margin-top: 50px; margin-bottom: 50px;">
    <div class="col-md-6 col-md-offset-3">
        <div class="panel panel-danger" style="border: 2px solid #660000;">
            <div class="panel-heading" style="background-color: #a94442; border-color: #660000; color: #fff;">
                <h3 class="panel-title text-center" style="font-family: Tahoma, sans-serif; font-weight: bold; letter-spacing: 1px;">[ 💀 НЕБЕЗПЕЧНА ЗОНА ]</h3>
            </div>
            <div class="panel-body text-center" style="background: #fff5f5;">
                <p style="color: #ff4444; font-weight: bold; text-transform: uppercase; margin-bottom: 20px;">Видалення акаунта призведе до повної втрати всіх ваших паст та кредитів!</p>
                
                <button type="button" class="btn btn-danger btn-block" data-toggle="collapse" data-target="#deleteForm" style="font-weight: bold;">
                    Я ХОЧУ ВИДАЛИТИ СВІЙ АКАУНТ
                </button>

                <div id="deleteForm" class="collapse" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px dashed #660000;">
                    <form action="settings.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_account">
                        
                        <?php $confirmed = isset($_SESSION['passkey_confirmed_delete']) || isset($_SESSION['oauth_confirmed_delete']); ?>
                        
                        <div class="form-group">
                            <label style="color: #333;">
                                <?php if ($confirmed): ?>
                                    Пароль більше не потрібен (особу підтверджено):
                                <?php else: ?>
                                    Введіть ваш пароль для підтвердження:
                                <?php endif; ?>
                            </label>
                            <input type="password" name="password" class="form-control" 
                                   style="border: 1px solid #ccc;" 
                                   <?= $confirmed ? '' : 'required' ?>
                                   placeholder="<?= $confirmed ? 'Можна залишити порожнім' : '*****' ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-lg btn-danger btn-block" onclick="return confirm('ВИ ВПЕВНЕНІ? Ця дія НЕЗВОРОТНА!')">
                            <?php if ($confirmed): ?>
                                💀 ОСТАТОЧНО ВИДАЛИТИ АКАУНТ 💀
                            <?php else: ?>
                                ПІДТВЕРДИТИ ПАРОЛЕМ ☠
                            <?php endif; ?>
                        </button>
                    </form>

                    <div style="margin-top: 15px; border-top: 1px dashed #444; padding-top: 15px;">
                        <p style="color: #888; font-size: 0.9em; margin-bottom: 10px;">Швидке підтвердження особи:</p>
                        
                        <?php if (isset($_SESSION['passkey_confirmed_delete']) || isset($_SESSION['oauth_confirmed_delete'])): ?>
                            <div class="alert alert-success" style="background: #1a3a1a; color: #44ff44; border: 1px solid #228822; padding: 10px; margin-bottom: 15px;">
                                <i class="glyphicon glyphicon-ok"></i> <b>ОСОБУ ПІДТВЕРДЖЕНО!</b><br>
                                <small>Тепер ви можете видалити акаунт кнопкою вище (пароль не потрібен).</small>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <?php if (count($myPasskeys) > 0): ?>
                                <div class="col-xs-12" style="margin-bottom: 10px;">
                                    <button type="button" class="btn btn-info btn-block passkey-btn" onclick="confirmDeleteAccountPasskey()" style="font-weight: bold;">
                                        ПІДТВЕРДИТИ ЧЕРЕЗ PASSKEY 🔑
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php if ($user->github_id): ?>
                                <div class="col-xs-12" style="margin-bottom: 10px;">
                                    <a href="api/oauth.php?provider=github&confirm_delete_oauth=1" class="btn btn-default btn-block" style="background: #333; color: #fff; border-color: #555; font-weight: bold;">
                                        ПІДТВЕРДИТИ ЧЕРЕЗ GITHUB 🐙
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($user->telegram_id): ?>
                                <div class="col-xs-12" style="margin-bottom: 10px;">
                                    <div class="text-center" style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                                        <p style="color: #aaa; margin-bottom: 5px;"><small>ПІДТВЕРДИТИ ЧЕРЕЗ TELEGRAM:</small></p>
                                        <script async src="https://telegram.org/js/telegram-widget.js?22" 
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

