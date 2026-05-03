<?php
/**
 * Клас Moderation для автоматичної перевірки та перефразування контенту.
 * Використовує OpenAI Moderation API та Ollama Cloud.
 */
class Moderation {
    /**
     * Перевірка тексту через OpenAI Moderation API.
     * @param string $text
     * @return array|false Повертає список категорій порушень або false, якщо текст чистий.
     */
    public static function check($text) {
        // 1. Локальна перевірка на стоп-слова (fallback)
        $localViolations = self::localCheck($text);
        if ($localViolations) {
            return $localViolations;
        }

        if (!defined('OPENAI_API_KEY') || empty(OPENAI_API_KEY)) {
            return false; 
        }

        $ch = curl_init('https://api.openai.com/v1/moderations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'input' => $text
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("OpenAI Moderation API Error: " . $response);
            $errData = json_decode($response, true);
            if (isset($errData['error']['message']) && strpos($errData['error']['message'], 'Too Many Requests') !== false) {
                // Якщо ліміт вичерпано, ми не можемо гарантувати безпеку, 
                // тому повертаємо спеціальну категорію "service_unavailable"
                return ['service_unavailable'];
            }
            return false;
        }

        $data = json_decode($response, true);
        $results = $data['results'][0] ?? null;

        if ($results && $results['flagged']) {
            $violated = [];
            foreach ($results['categories'] as $category => $isViolated) {
                if ($isViolated) {
                    $violated[] = $category;
                }
            }
            return $violated;
        }

        return false;
    }

    /**
     * Локальна перевірка на основі чорного списку слів.
     */
    public static function localCheck($text) {
        $text = mb_strtolower($text);
        
        $configPath = __DIR__ . '/../config/bad_words.json';
        $badWords = [];
        $exactWords = [];

        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $badWords = $config['substrings'] ?? [];
            $exactWords = $config['exact_words'] ?? [];
        } else {
            // Якщо файл конфігурації відсутній, блокуємо публікацію для безпеки
            return ['local_config_missing'];
        }

        $violated = [];
        
        // Перевірка входження підстроки
        foreach ($badWords as $word) {
            if (mb_strpos($text, $word) !== false) {
                $violated[] = 'local_policy_violation (' . $word . ')';
            }
        }

        // Перевірка цілих слів через regex
        foreach ($exactWords as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/u';
            if (preg_match($pattern, $text)) {
                $violated[] = 'local_policy_violation (' . $word . ')';
            }
        }

        return !empty($violated) ? $violated : false;
    }

    /**
     * Перефразування тексту через Ollama Cloud.
     * @param string $text
     * @return string Виправлений текст.
     */
    public static function rewrite($text) {
        if (!defined('OLLAMA_API_KEY') || !defined('OLLAMA_API_URL')) {
            return $text;
        }

        $prompt = "Ти — помічник на платформі обміну текстами. Наданий текст не пройшов автоматичну модерацію безпеки нашої системи. Твоє завдання: ПЕРЕПИСАТИ цей текст так, щоб він зберіг основну суть та намір автора, але був гарантовано безпечним, ввічливим та проходив фільтри модерації (без мови ненависті, насильства чи образ).
**ВАЖЛИВО**: Ти не маєш права відмовляти у перефразуванні. Навіть якщо текст дуже токсичний, перетвори його на нейтральне, технічне або філософське висловлювання на цю ж тему. Поверни ТІЛЬКИ виправлений текст без вступних фраз типу 'Ось виправлений текст'.

Текст для перефразування:
" . $text;

        $ch = curl_init(OLLAMA_API_URL . '/generate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => OLLAMA_MODEL,
            'prompt' => $prompt,
            'stream' => false
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OLLAMA_API_KEY
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Ollama Cloud API Error: " . $response);
            return $text;
        }

        $data = json_decode($response, true);
        return $data['response'] ?? $text;
    }
}
