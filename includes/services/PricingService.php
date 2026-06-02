<?php
/**
 * PricingService — централізована тарифна сітка.
 *
 * Єдине місце для визначення вартості кредитів.
 * Змінювати тарифи потрібно тільки тут.
 */
class PricingService {

    // ── Telegram Stars → кредити ──

    /**
     * Тарифи для Telegram Stars.
     * Ключ: кількість ⭐, значення: ['credits' => ..., 'label' => ..., 'description' => ...]
     */
    const TG_STARS_TARIFFS = [
        50  => ['credits' => 100,  'label' => 'Базовий',    'description' => '100 кредитів'],
        200 => ['credits' => 500,  'label' => 'Стандартний', 'description' => '500 кредитів'],
        500 => ['credits' => 1500, 'label' => 'Преміум',     'description' => '1500 кредитів'],
    ];

    /**
     * Отримати кількість кредитів за Telegram Stars.
     *
     * @param int $stars Кількість ⭐ (50, 200, 500)
     * @return int Кількість кредитів (0 = невідомий тариф)
     */
    public static function creditsForStars(int $stars): int {
        if ($stars >= 500) return 1500;  // 500+ ⭐ = преміум
        return self::TG_STARS_TARIFFS[$stars]['credits'] ?? 0;
    }

    /**
     * Отримати деталі тарифу за кількістю ⭐.
     *
     * @return array{label: string, credits: int, description: string}|null
     */
    public static function tariffForStars(int $stars): ?array {
        return self::TG_STARS_TARIFFS[$stars] ?? null;
    }

    /**
     * Всі тарифи Telegram Stars як масив для inline keyboard.
     *
     * @return array<int, array{credits: int, label: string}>
     */
    public static function getStarsTariffs(): array {
        return self::TG_STARS_TARIFFS;
    }

    /**
     * Сформувати inline keyboard для вибору тарифу в Telegram.
     *
     * @param string $orderId ID замовлення
     * @return array Масив inline_keyboard для Telegram API
     */
    public static function getStarsInlineKeyboard(string $orderId): array {
        $buttons = [];
        foreach (self::TG_STARS_TARIFFS as $stars => $t) {
            $buttons[] = [
                [
                    'text' => "🥉 {$t['label']} - {$stars} ⭐ ({$t['credits']} кре.)",
                    'callback_data' => "tariff_{$stars}_{$orderId}"
                ]
            ];
        }
        return ['inline_keyboard' => $buttons];
    }

    // ── Donatello (UAH) → кредити ──

    /**
     * Тарифи для Donatello (UAH).
     * Ключ: мінімальна сума в UAH, значення: кредити.
     * Сортувати за спаданням суми!
     */
    const DONATELLO_TARIFFS = [
        250 => 1500,
        100 => 500,
        25  => 100,
    ];

    /**
     * Отримати кількість кредитів за суму в UAH.
     *
     * @param float $amount Сума в UAH
     * @return int Кількість кредитів
     */
    public static function creditsForDonatello(float $amount): int {
        foreach (self::DONATELLO_TARIFFS as $minAmount => $credits) {
            if ($amount >= $minAmount) {
                return $credits;
            }
        }
        // Для довільних сум: 4 кредити за 1 UAH
        return (int)floor($amount * 4);
    }

    // ── Загальне ──

    /**
     * Повертає список тарифів для відображення на сторінці credits.php.
     *
     * @return array<array{credits: int, price_uah: string, price_stars: int, label: string}>
     */
    public static function getDisplayTariffs(): array {
        return [
            [
                'credits'     => 100,
                'price_uah'   => '25',
                'price_stars' => 50,
                'label'       => 'Базовий',
            ],
            [
                'credits'     => 500,
                'price_uah'   => '100',
                'price_stars' => 200,
                'label'       => 'Стандартний',
            ],
            [
                'credits'     => 1500,
                'price_uah'   => '250',
                'price_stars' => 500,
                'label'       => 'Преміум',
            ],
        ];
    }
}
