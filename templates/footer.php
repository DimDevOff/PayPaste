</div> <!-- Кінець контейнера -->

<footer class="text-center" style="margin-top: 50px; padding: 20px;">
    <p>PayPaste &copy; 1776613860-<?= time() ?> (Unix)</p>
    <p>Зроблено за вимогами курсу "Вебтехнології". Всі права можливо захищені</p>
    <div class="old-school-badges" style="margin-top: 15px; margin-bottom: 15px; opacity: 0.9; image-rendering: pixelated;">
        <a title="Valid HTML 4.01" href="https://www.w3.org/TR/html401/"><img src="https://www.w3.org/Icons/valid-html401" alt="Valid HTML 4.01!" height="31" width="88" style="border:0; margin: 2px;"></a>
        <a title="Powered by PHP" href="https://php.net"><img src="https://php.net/images/news/php-powered.png" alt="Powered by PHP" height="41" width="88" style="border:0; margin: 2px;"></a>
        <a title="Privacy Guaranteed" href="#"><img src="https://img.shields.io/badge/Privacy-Guaranteed-green?style=plastic" alt="Privacy Guaranteed" height="31" width="88" style="border:0; margin: 2px;"></a>
        <a title="Made with NeoVim" href="https://neovim.io/"><img src="https://img.shields.io/badge/Made_with-NeoVim-019733?style=plastic&logo=neovim&logoColor=white" alt="NeoVim" height="31" width="88" style="border:0; margin: 2px;"></a>
        
        <br>

        <a title="Optimal 800x600" href="#"><img src="https://img.shields.io/badge/800x600-Optimal-blue?style=plastic" alt="800x600" height="31" width="88" style="border:0; margin: 2px;"></a>
        <a title="IE 6 Compatible" href="#"><img src="https://img.shields.io/badge/IE_6-Compatible-blue?style=plastic" alt="IE6" height="31" width="88" style="border:0; margin: 2px;"></a>
        <a title="No Spyware" href="#"><img src="https://img.shields.io/badge/No_Spyware-Clean-brightgreen?style=plastic" alt="Safe" height="31" width="88" style="border:0; margin: 2px;"></a>
        <a title="Anti-Spam Protected" href="#"><img src="https://img.shields.io/badge/Anti--Spam-Protected-red?style=plastic" alt="AntiSpam" height="31" width="88" style="border:0; margin: 2px;"></a>
    </div>

    <!-- Adsterra: Рекламні блоки -->
    <?php if (!isset($hide_ads) || !$hide_ads): ?>
    <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; align-items: center;">
        <!-- Banner 300×250 -->
        <div style="overflow: hidden;">
            <script nonce="<?= csp_nonce() ?>">
                atOptions = {
                    'key' : '<?= ADSTERRA_300x250_KEY ?>',
                    'format' : 'iframe',
                    'height' : 250,
                    'width' : 300,
                    'params' : {}
                };
            </script>
            <script nonce="<?= csp_nonce() ?>" src="<?= ADSTERRA_INVOKE_BASE_URL ?>/<?= ADSTERRA_300x250_KEY ?>/invoke.js"></script>
        </div>
    </div>
    <?php endif; ?>

    <!-- Adsterra: Popunder (глобально) -->
    <?php if (!isset($hide_ads) || !$hide_ads): ?>
    <script nonce="<?= csp_nonce() ?>" src="<?= ADSTERRA_POPUNDER_URL ?>"></script>
    <?php endif; ?>
</footer>

<script nonce="<?= csp_nonce() ?>" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script nonce="<?= csp_nonce() ?>" src="assets/js/app.js"></script>
<script nonce="<?= csp_nonce() ?>" src="assets/js/passkey.js?v=1.4"></script>
<script nonce="<?= csp_nonce() ?>" src="assets/js/theme-switch.js?v=1.0"></script>
</body>
</html>

