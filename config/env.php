<?php

class EnvLoader {
    /**
     * Завантажує змінні з .env файлу в системні змінні ($_ENV, $_SERVER).
     * Ігнорує коментарі (рядки, що починаються з #) та порожні рядки.
     * Автоматично обробляє лапки навколо значень.
     *
     * @param string $path Шлях до .env файлу
     * @return void
     */
    public static function load($path) {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2) + [NULL, NULL];

            if ($name !== null && $value !== null) {
                $name = trim($name);
                $value = trim($value);

                // Видалити лапки, якщо вони є
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Завантажуємо налаштування при підключенні
EnvLoader::load(__DIR__ . '/../.env');
