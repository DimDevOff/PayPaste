<div class="container" style="margin-top: 50px; max-width: 500px;">
    <div class="panel panel-warning" style="border: 2px solid #ffcc00; box-shadow: 5px 5px 0px #ffcc00;">
        <div class="panel-heading" style="background: #ffcc00; color: #000; font-weight: bold; border-bottom: 2px solid #000;">
            <h3 class="panel-title" style="font-weight: bold; font-family: monospace;">[ ПІДТВЕРДЖЕННЯ ПОШТИ ]</h3>
        </div>
        <div class="panel-body" style="background: #222; color: #eee; font-family: monospace;">
            
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger" style="background: #ff0000; color: #fff; border: 2px solid #fff; border-radius: 0;">
                    <strong>[ ПОМИЛКА ]</strong> <?= htmlspecialchars($_SESSION['error_msg']) ?>
                </div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="alert alert-success" style="background: #00ff00; color: #000; border: 2px solid #fff; border-radius: 0;">
                    <strong>[ УСПІХ ]</strong> <?= htmlspecialchars($_SESSION['success_msg']) ?>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>

            <div style="margin-bottom: 20px; text-align: center; border: 1px dashed #555; padding: 15px; background: #111;">
                <p style="color: #eee;">На вашу адресу <strong><!--email_off--><?= htmlspecialchars($user->email) ?><!--/email_off--></strong> було надіслано код підтвердження.</p>
                <p style="color: #ffcc00; font-size: 0.9em;">Будь ласка, введіть його нижче, щоб розблокувати доступ до акаунту.</p>
            </div>

            <form method="POST" action="verify.php" style="margin-bottom: 20px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify_code">
                
                <div class="form-group">
                    <label>6-значний код:</label>
                    <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" required 
                           style="background: #000; color: #0f0; border: 1px solid #555; font-family: monospace; font-size: 24px; text-align: center; letter-spacing: 5px;">
                </div>
                
                <button type="submit" class="btn btn-warning btn-block" style="border: 2px solid #fff; border-radius: 0; font-weight: bold; font-family: monospace;">
                    > ПІДТВЕРДИТИ
                </button>
            </form>

            <hr style="border-top: 1px dashed #555;">

            <form method="POST" action="verify.php" class="text-center">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resend_code">
                <p style="font-size: 0.9em; color: #888;">Не отримали листа або термін дії коду минув?</p>
                <button type="submit" class="btn btn-default btn-xs" style="background: #333; color: #fff; border: 1px solid #555; border-radius: 0; font-family: monospace;">
                    [ НАДІСЛАТИ НОВИЙ КОД ]
                </button>
            </form>
            
            <div class="text-center" style="margin-top: 15px;">
                <a href="login.php?logout=1" style="color: #ff4444; text-decoration: underline; font-size: 0.9em;">[ ВИЙТИ З АКАУНТУ ]</a>
            </div>

        </div>
    </div>
</div>
