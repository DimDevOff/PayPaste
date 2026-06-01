/**
 * PayPaste — Перемикач кольорових тем
 * Миттєвий попередній перегляд теми без перезавантаження сторінки.
 */
(function() {
    'use strict';

    var allowed = ['retro', 'dark', 'terminal', 'light', 'github', 'retro-green'];

    // Оновлення візуальної активності картки
    function updateActiveCard(targetRadio) {
        document.querySelectorAll('.theme-card').forEach(function(c) {
            c.classList.remove('theme-card-active');
        });
        var card = targetRadio.closest('.theme-card');
        if (card) {
            card.classList.add('theme-card-active');
        }
    }

    // Застосувати тему на <html>
    function applyTheme(theme) {
        if (allowed.indexOf(theme) !== -1) {
            document.documentElement.setAttribute('data-theme', theme);
        } else {
            console.warn('[Тема] Невідома тема:', theme);
        }
    }

    // Обробник change на всі radio-кнопки тем (спрацьовує і нативно через <label>)
    document.querySelectorAll('input[name="theme"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            applyTheme(this.value);
            updateActiveCard(this);
        });
    });

    // Клік на картку — просто оновлює активний вигляд
    // (сам radio вже обраний нативно через <label>, change вже викликаний)
    document.querySelectorAll('.theme-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var radio = this.querySelector('input[name="theme"]');
            if (radio && radio.checked) {
                updateActiveCard(radio);
            }
        });
    });
})();
