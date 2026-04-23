<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <h2 style="border-bottom: 2px solid #ccc; padding-bottom: 5px;">💰 Поповнення балансу кредитів</h2>
        
        <div class="panel panel-default" style="margin-top: 20px;">
            <div class="panel-heading" style="background: #f5f5f5;">
                <h4 style="margin: 0;">Ваш поточний баланс: <strong class="text-success"><?= $user->credits ?> кредитів</strong></h4>
            </div>
            <div class="panel-body">
                <p class="text-muted">Кредити використовуються для створення та перегляду платних паст. Оберіть зручний спосіб оплати:</p>
            </div>
        </div>

        <!-- Тарифи -->
        <div class="row" style="margin-bottom: 30px;">
            <div class="col-sm-4">
                <div class="panel panel-info text-center">
                    <div class="panel-heading"><h4 style="margin:0;">🥉 Базовий</h4></div>
                    <div class="panel-body">
                        <h2 style="color:#337ab7; margin:5px 0;">100 кредитів</h2>
                        <p class="text-muted" style="font-size:24px; font-weight:bold;">25 ₴</p>
                        <small class="text-muted">або 50 ⭐ Telegram Stars</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="panel panel-success text-center" style="border-width: 2px;">
                    <div class="panel-heading" style="position:relative;">
                        <h4 style="margin:0;">🥈 Стандартний</h4>
                        <span class="label label-danger" style="position:absolute; top:-10px; right:-10px;">Популярний!</span>
                    </div>
                    <div class="panel-body">
                        <h2 style="color:#3c763d; margin:5px 0;">500 кредитів</h2>
                        <p class="text-muted" style="font-size:24px; font-weight:bold;">100 ₴</p>
                        <small class="text-muted">або 200 ⭐ Telegram Stars</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="panel panel-warning text-center">
                    <div class="panel-heading"><h4 style="margin:0;">🥇 Преміум</h4></div>
                    <div class="panel-body">
                        <h2 style="color:#8a6d3b; margin:5px 0;">1500 кредитів</h2>
                        <p class="text-muted" style="font-size:24px; font-weight:bold;">250 ₴</p>
                        <small class="text-muted">або 500 ⭐ Telegram Stars</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Способи оплати -->
        <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;">Оберіть спосіб оплати:</h3>

        <div class="row" style="margin-top: 20px;">
            <!-- Donatello -->
            <div class="col-sm-6">
                <div class="panel panel-default text-center" style="border: 2px solid #ff6b6b;">
                    <div class="panel-heading" style="background: #fff0f0;">
                        <h4 style="margin: 0; color: #e74c3c;">🎁 Donatello</h4>
                    </div>
                    <div class="panel-body">
                        <p>Оплата через сервіс Donatello (картка, Google Pay, Apple Pay)</p>
                        <button type="button" class="btn btn-danger btn-lg btn-block" id="btn-donatello" style="font-weight: bold;">
                            💳 Оплатити через Donatello
                        </button>
                        <small class="text-muted" style="display:block; margin-top:8px;">Комісія сервісу ~5%</small>
                    </div>
                </div>
            </div>

            <!-- Telegram Stars -->
            <div class="col-sm-6">
                <div class="panel panel-default text-center" style="border: 2px solid #0088cc;">
                    <div class="panel-heading" style="background: #e8f4fd;">
                        <h4 style="margin: 0; color: #0088cc;">⭐ Telegram Stars</h4>
                    </div>
                    <div class="panel-body">
                        <p>Миттєва оплата зірками Telegram через бота</p>
                        <a href="https://t.me/PayPastePayBot?start=<?= $order_id ?>" target="_blank" class="btn btn-primary btn-lg btn-block" id="tg-stars-link" style="font-weight: bold; background: #0088cc; border-color: #0077b5;">
                            ⭐ Оплатити через Telegram Stars
                        </a>
                        <small class="text-muted" style="display:block; margin-top:8px;">Відкриється в новій вкладці</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Інструкція -->
        <div class="well" style="background: #fffbe6; border: 1px solid #f0d000; margin-top: 20px;">
            <h4>📋 Як це працює?</h4>
            <ol>
                <li>Оберіть спосіб оплати та тариф</li>
                <li>При оплаті через <strong>Donatello</strong> — обов'язково вкажіть ваш код замовлення <code><?= $order_id ?></code> у повідомленні</li>
                <li>При оплаті через <strong>Telegram Stars</strong> — бот автоматично прив'яже замовлення</li>
                <li>Кредити зараховуються протягом 5 хвилин після підтвердження оплати</li>
            </ol>
            <p class="text-muted"><small>Ваш код замовлення: <strong><?= $order_id ?></strong></small></p>
        </div>

        <!-- Статус перевірки оплати -->
        <div id="payment-status" style="display: none; margin-top: 20px;">
            <div class="alert alert-info">
                <span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Перевірка статусу оплати...
            </div>
        </div>
    </div>
</div>

<!-- Модальне вікно успішної оплати -->
<div class="modal fade" id="successModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border: 3px solid #28a745;">
            <div class="modal-header" style="background: #28a745; color: #fff;">
                <h4 class="modal-title">✅ Оплата успішно пройдена!</h4>
            </div>
            <div class="modal-body text-center">
                <h3 style="color: #28a745;">Вітаємо!</h3>
                <p class="text-success">Ваш баланс поповнено на <strong id="credits-earned">0</strong> кредитів</p>
                <p>Поточний баланс: <strong id="new-balance">0</strong> кредитів</p>
            </div>
            <div class="modal-footer text-center">
                <p class="text-muted">Перезавантаження через <span id="countdown">10</span> секунди...</p>
            </div>
        </div>
    </div>
</div>

<!-- Модальне вікно попередження для Donatello -->
<div class="modal fade" id="donatelloModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border: 3px solid #e74c3c;">
            <div class="modal-header" style="background: #e74c3c; color: #fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title">⚠️ ВАЖЛИВО! Прочитайте перед оплатою!</h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" style="border: 2px solid #c0392b; font-size: 15px;">
                    <p><strong>🚨 Увага!</strong> При оплаті через Donatello ви <u>ОБОВ'ЯЗКОВО</u> повинні вказати у полі "Повідомлення" ваш код замовлення:</p>
                    <div class="text-center" style="margin: 15px 0;">
                        <code style="font-size: 22px; padding: 10px 20px; background: #fff3cd; border: 2px dashed #d58512; display: inline-block;" id="order-code-display"><?= $order_id ?></code>
                        <br>
                        <button type="button" class="btn btn-xs btn-default" style="margin-top: 8px;" onclick="copyOrderCode()">📋 Скопіювати код</button>
                    </div>
                    <p style="color: #c0392b; font-weight: bold;">❌ Якщо ви НЕ вкажете цей код — ми не зможемо ідентифікувати ваш платіж, і <span style="text-decoration: underline;">гроші будуть втрачені без можливості повернення!</span></p>
                </div>
                <p class="text-muted text-center">Переконайтесь, що скопіювали код перед переходом на сторінку оплати.</p>
            </div>
            <div class="modal-footer" style="text-align: center;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Скасувати</button>
                <a href="<?= getenv('DONATELLO_URL') ?: 'https://donatello.to/YOUR_PAGE' ?>" target="_blank" class="btn btn-danger btn-lg" id="donatello-confirm-btn" style="font-weight: bold;">
                    ✅ Я скопіював код — Перейти до оплати
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Відкриття модального вікна при натисканні на Donatello
document.getElementById('btn-donatello').addEventListener('click', function() {
    $('#donatelloModal').modal('show');
});

// Копіювання коду замовлення в буфер обміну
function copyOrderCode() {
    var code = document.getElementById('order-code-display').innerText;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(function() {
            alert('✅ Код скопійовано: ' + code);
        });
    } else {
        // Фолбек для старих браузерів
        var tmp = document.createElement('textarea');
        tmp.value = code;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        alert('✅ Код скопійовано: ' + code);
    }
}

// Polling для перевірки статусу оплати
$(document).ready(function() {
    var orderId = '<?= $order_id ?>';
    var pollingInterval = null;
    var secondsLeft = 5;
    var isPolling = false;

    function startPolling() {
        if (isPolling) return;
        isPolling = true;
        $('#payment-status').show();
        pollingInterval = setInterval(checkOrderStatus, 3000);
        // Перевірка одразу
        checkOrderStatus();
    }

    function checkOrderStatus() {
        $.getJSON('api/check_order_status.php', { order_id: orderId }, function(data) {
            if (!data || !data.exists) {
                return;
            }

            if (data.status === 'completed') {
                clearInterval(pollingInterval);
                isPolling = false;

                $('#payment-status').html(
                    '<div class="alert alert-success" style="margin-bottom:0;">' +
                    '<span class="glyphicon glyphicon-ok"></span> Оплата підтверджена!</div>'
                );

                $('#credits-earned').text(data.amount_credits);
                $('#new-balance').text(data.current_balance);

                $('#successModal').modal({
                    backdrop: 'static',
                    keyboard: false
                });

                var countdownInterval = setInterval(function() {
                    secondsLeft--;
                    $('#countdown').text(secondsLeft);

                    if (secondsLeft <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = 'index.php';
                    }
                }, 1000);
            }
        }).fail(function() {
            // Помилки мережі
        });
    }

    // Запуск перевірки при натисканні на кнопки оплати
    $('#btn-donatello').on('click', startPolling);
    $('#tg-stars-link').on('click', function() {
        setTimeout(startPolling, 2000);
    });
});
</script>
