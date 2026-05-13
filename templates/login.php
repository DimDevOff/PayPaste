<?php
$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
?>
<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <div class="panel panel-primary">
            <div class="panel-heading"><h3 class="panel-title text-center" id="auth-title">Створення акаунту</h3></div>
            <div class="panel-body">
                
                <!-- Соціальні мережі -->
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-xs-12 col-sm-6" style="margin-bottom: 10px;">
                        <a href="api/oauth.php?provider=github" class="btn btn-default btn-block" style="border:1px solid var(--border-color); height: 40px; display: flex; align-items: center; justify-content: center;">
                            🐙 GitHub
                        </a>
                    </div>
                    <div class="col-xs-12 col-sm-6 text-center">
                        <script nonce="<?= csp_nonce() ?>" async src="https://telegram.org/js/telegram-widget.js?21" data-telegram-login="PayPasteBot" data-auth-url="<?= rtrim(APP_URL, '/') ?>/api/oauth.php?provider=telegram" data-request-access="write" data-size="large"></script>
                    </div>
                </div>
                
                <!-- Passkey -->
                <div style="margin-bottom: 15px; display:none;" class="login-only">
                    <button type="button" id="btn-passkey-login" class="btn btn-warning btn-block passkey-btn" style="font-weight:bold; border: 1px dashed var(--accent);">
                        🔑 Увійти через Passkey
                    </button>
                </div>

                <div style="margin-bottom: 20px;" class="reg-only">
                    <button type="button" id="btn-passkey-register" class="btn btn-warning btn-block passkey-btn" style="font-weight:bold; border: 1px dashed var(--accent);">
                        🔑 Зареєструватись через Passkey
                    </button>
                </div>

                <div id="passkey-error" class="alert alert-danger" style="display:none; margin-bottom: 15px;"></div>
                <div id="passkey-success" class="alert alert-success" style="display:none; margin-bottom: 15px;"></div>

                <hr style="border-top: 1px dashed var(--border-color);">

                <form action="login.php" method="POST" id="auth-form">
                    <?= csrf_field() ?>
                    <p class="text-muted text-center"><small id="form-subtitle">Заповніть нижче для створення акаунту</small></p>
                    
                    <div class="form-group">
                        <label>E-mail:</label>
                        <input type="email" name="email" class="form-control" required placeholder="example@mail.com" value="<?= htmlspecialchars($old['email'] ?? $_COOKIE['remember_email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group reg-only">
                        <label>Нік:</label>
                        <input type="text" name="nickname" class="form-control" placeholder="Крутий_Хакер_2007" value="<?= htmlspecialchars($old['nickname'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Пароль:</label>
                        <input type="password" name="password" class="form-control" required placeholder="*****">
                    </div>
                    
                    <div class="form-group reg-only">
                        <label>Підтвердження паролю:</label>
                        <input type="password" name="password_confirm" class="form-control" placeholder="*****">
                    </div>

                    <div class="checkbox login-only" style="display:none; margin-bottom: 15px;">
                        <label>
                            <input type="checkbox" name="remember" value="1" <?= isset($_COOKIE['remember_me']) ? 'checked' : '' ?>> <strong>Запам'ятати мене на 14 днів</strong>
                        </label>
                    </div>

                    <div style="margin-top: 20px;">
                        <div class="reg-only">
                            <button type="submit" name="action" value="register" class="btn btn-success btn-lg btn-block">Зареєструватись</button>
                        </div>
                        <div class="login-only" style="display:none;">
                            <button type="submit" name="action" value="login" class="btn btn-primary btn-lg btn-block">Увійти</button>
                        </div>
                    </div>
                </form>

                <div class="text-center" style="margin-top: 20px;">
                    <div class="reg-only">
                        Вже маєте акаунт? <a href="#" id="link-to-login">Увійти сюди</a>
                    </div>
                    <div class="login-only" style="display:none;">
                        Ще немає акаунту? <a href="#" id="link-to-register">Зареєструватись</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
    function switchToLogin(e) {
        if(e) e.preventDefault();
        
        // Відображення тільки елементів входу
        document.querySelectorAll('.reg-only').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.login-only').forEach(el => el.style.display = 'block');
        
        // Оновлення текстів
        document.getElementById('auth-title').innerText = 'Вхід до системи';
        document.getElementById('form-subtitle').innerText = 'З поверненням! Введіть логін та пароль';
        
        // Оновлюємо URL для можливості поділитися посиланням на вхід/реєстрацію
        const url = new URL(window.location);
        url.searchParams.set('mode', 'login');
        window.history.replaceState({}, '', url);
    }

    function switchToRegister(e) {
        if(e) e.preventDefault();
        
        // Відображення тільки елементів реєстрації
        document.querySelectorAll('.login-only').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.reg-only').forEach(el => el.style.display = 'block');
        
        // Оновлення текстів
        document.getElementById('auth-title').innerText = 'Створення акаунту';
        document.getElementById('form-subtitle').innerText = 'Заповніть нижче для створення акаунту';
        
        const url = new URL(window.location);
        url.searchParams.set('mode', 'register');
        window.history.replaceState({}, '', url);
    }

    // Обробники подій (замість inline onclick)
    document.getElementById('btn-passkey-login').addEventListener('click', function() { loginWithPasskey(); });
    document.getElementById('btn-passkey-register').addEventListener('click', function() { registerPasskey(); });
    document.getElementById('link-to-login').addEventListener('click', switchToLogin);
    document.getElementById('link-to-register').addEventListener('click', switchToRegister);

    // Перевірка режиму при завантаженні
    window.addEventListener('DOMContentLoaded', (event) => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('mode') === 'login') {
            switchToLogin();
        } else {
            switchToRegister();
        }
    });
</script>

