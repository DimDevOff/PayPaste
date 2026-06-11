<?php
/**
 * HttpClient — єдиний HTTP-клієнт для зовнішніх API.
 *
 * Замінює прямі curl_init/curl_exec/curl_close в 3+ місцях.
 * Підтримує: POST, JSON, Authorization, таймаути, логування помилок.
 *
 * Використання:
 *   $client = new HttpClient();
 *   $result = $client->postJson('https://api.example.com', ['key' => 'value'], [
 *       'Authorization: Bearer token123'
 *   ], 30);
 */
class HttpClient {

    /**
     * Виконати POST-запит з JSON-тілом.
     *
     * @param string   $url     URL ендпоінта
     * @param array    $data    Дані для JSON-тіла
     * @param array    $headers Додаткові HTTP-заголовки (напр. ['Authorization: Bearer ...'])
     * @param int      $timeout Таймаут у секундах
     * @return array{body: string, http_code: int} Відповідь
     * @throws \RuntimeException
     */
    public function postJson(string $url, array $data, array $headers = [], int $timeout = 30): array {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException("HttpClient: не вдалося закодувати JSON: " . json_last_error_msg());
        }

        $defaultHeaders = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $allHeaders,
        ]);

        $body    = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("HTTP-запит до $url: cURL помилка: $curlErr");
        }

        return [
            'body'      => $body,
            'http_code' => $httpCode,
        ];
    }

    /**
     * POST + JSON + перевірка HTTP 200 (для OpenAI, Ollama тощо).
     *
     * @return array{body: string, http_code: int}
     * @throws \RuntimeException якщо не 200
     */
    public function postJsonExpect200(string $url, array $data, array $headers = [], int $timeout = 30): array {
        $result = $this->postJson($url, $data, $headers, $timeout);

        if ($result['http_code'] !== 200) {
            throw new \RuntimeException(
                "API $url повернув HTTP {$result['http_code']}: {$result['body']}"
            );
        }

        return $result;
    }

    /**
     * POST + JSON + перевірка HTTP 2xx (для Resend тощо).
     *
     * @return array{body: string, http_code: int}
     * @throws \RuntimeException якщо не 2xx
     */
    public function postJsonExpectSuccess(string $url, array $data, array $headers = [], int $timeout = 30): array {
        $result = $this->postJson($url, $data, $headers, $timeout);

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            throw new \RuntimeException(
                "API $url повернув HTTP {$result['http_code']}: {$result['body']}"
            );
        }

        return $result;
    }

    /**
     * Розпарсити JSON-відповідь.
     *
     * @return array|null
     */
    public function parseJson(string $body): ?array {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("HttpClient: помилка парсингу JSON: " . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Виконати GET-запит.
     *
     * @param string $url     URL ендпоінта
     * @param array  $headers Додаткові HTTP-заголовки
     * @param int    $timeout Таймаут у секундах
     * @return array{body: string, http_code: int}
     * @throws \RuntimeException
     */
    public function getJson(string $url, array $headers = [], int $timeout = 30): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("HTTP-запит до $url: cURL помилка: $curlErr");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException(
                "API $url повернув HTTP {$httpCode}: {$body}"
            );
        }

        return [
            'body'      => $body,
            'http_code' => $httpCode,
        ];
    }
}
