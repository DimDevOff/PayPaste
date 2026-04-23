$(document).ready(function() {

    // Надійна логіка копіювання з використанням делегування подій
    $(document).on('click', '#copy-btn', function(e) {
        e.preventDefault();
        
        const textAreaSource = document.getElementById('paste-textarea');
        if (!textAreaSource) {
            console.error("PayPaste JS: Текстове поле джерела не знайдено!");
            return;
        }

        const textToCopy = textAreaSource.value;
        const $msg = $('#copy-msg');

        function showSuccess() {
            $msg.stop(true, true).fadeIn().css('display', 'inline-block').delay(2000).fadeOut();
        }

        // Спробувати сучасний API спочатку
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textToCopy).then(showSuccess).catch(err => {
                console.warn("PayPaste JS: Clipboard API не вдався, пробуємо запасний варіант...", err);
                doFallback(textToCopy);
            });
        } else {
            console.log("PayPaste JS: Сучасний API недоступний, використовуємо запасний варіант.");
            doFallback(textToCopy);
        }

        function doFallback(text) {
            const t = document.createElement("textarea");
            t.value = text;
            t.style.position = "fixed";
            t.style.opacity = "0";
            document.body.appendChild(t);
            t.focus();
            t.select();
            try {
                const ok = document.execCommand('copy');
                if (ok) {
                    showSuccess();
                } else {
                    console.error("PayPaste JS: execCommand('copy') повернув false");
                }
            } catch (err) {
                console.error("PayPaste JS: Помилка запасного варіанту:", err);
            }
            document.body.removeChild(t);
        }
    });
});

// Логіка квесту Adsterra
$(document).ready(function() {
    const $btn = $('#start-quest-btn');
    if ($btn.length > 0) {
        $btn.on('click', function() {
            $(this).hide();
            $('#quest-timer-container').show();
            
            let timeLeft = 10;
            const $timer = $('#quest-timer');
            const $bar = $('#quest-progress-bar');
            
            const questInterval = setInterval(function() {
                timeLeft--;
                $timer.text(timeLeft);
                $bar.css('width', ((10 - timeLeft) * 10) + '%');
                
                if (timeLeft <= 0) {
                    clearInterval(questInterval);
                    
                    // Відправка AJAX на підтвердження
                    $.ajax({
                        url: 'api/webhooks/verify_ad.php',
                        method: 'POST',
                        dataType: 'json',
                        success: function(data) {
                            if (data.success) {
                                $('#ads-count').text(data.ads_watched);
                                if (data.done) {
                                    alert('✅ Вітаємо! Квест пройдено. Доступ відкрито!');
                                    location.reload();
                                } else {
                                    alert('✅ Рекламу зараховано! Залишилося: ' + data.remaining);
                                    $('#quest-timer-container').hide();
                                    $btn.show().removeClass('blink-text').text('📺 ПЕРЕГЛЯНУТИ НАСТУПНУ (10 сек)');
                                    $timer.text('10');
                                    $bar.css('width', '0%');
                                }
                            }
                        },
                        error: function() {
                            alert('❌ Помилка при підтвердженні. Спробуйте ще раз.');
                            $('#quest-timer-container').hide();
                            $btn.show();
                        }
                    });
                }
            }, 1000);
        });
    }
});
