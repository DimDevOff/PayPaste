/**
 * PayPaste — Перемикач кольорових тем
 * Забезпечує миттєвий попередній перегляд теми без перезавантаження сторінки.
 */
(function() {
    'use strict';

    // Миттєвий перегляд теми при зміні radio-кнопки
    document.querySelectorAll('input[name="theme"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const allowed = ['retro', 'dark', 'terminal', 'light', 'github', 'retro-green'];
            if (allowed.indexOf(this.value) !== -1) {
                document.documentElement.setAttribute('data-theme', this.value);
            } else {
                console.warn('Невідома тема:', this.value);
            }
        });
    });

    // Підсвітка активної картки теми
    document.querySelectorAll('.theme-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var radio = this.querySelector('input[name="theme"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
                
                // Оновити візуальну активність
                document.querySelectorAll('.theme-card').forEach(function(c) {
                    c.classList.remove('theme-card-active');
                });
                this.classList.add('theme-card-active');
            }
        });
    });
})();
