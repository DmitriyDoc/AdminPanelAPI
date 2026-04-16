<?php

namespace App\Http\Controllers\Parser;

use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

use Exception;
class CurlConnectorController
{
    private $userAgents = [
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 11.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0",
        "Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15",
        "Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1",
        "Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0",
        "Mozilla/5.0 (Linux; Android 14; SM-G998B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36",
        "Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.2420.81",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15",
        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15",
        "Mozilla/5.0 (X11; Linux i686; rv:109.0) Gecko/20100101 Firefox/121.0"
    ];

    public function getCurlImages(array $urls)
    {
        $results = [];

        // Очистка и валидация
        $validUrls = array_filter(array_map('trim', $urls), fn($u) => filter_var($u, FILTER_VALIDATE_URL));
        if (empty($validUrls)) return $results;

        try {
            $bridgeUrl = 'http://spectrum_chromium_bridge:3002/fetch';

            $payload = [
                'urls' => array_values($validUrls),
                'options' => [
                    'timeout' => 45000, // Передаем подсказку мосту (хотя мост теперь сам решает)
                    'concurrency' => 3, // Снизили параллельность, чтобы не валить браузер
                    'waitUntil' => 'networkidle2',
                    'blockResources' => false
                ]
            ];

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    // ВАЖНО: Увеличиваем таймаут PHP, чтобы дождаться ретраев в Node.js
                    // 3 URL * ~20-30 сек (с ретраем) = нужно около 90 сек запаса
                    'timeout' => 120,
                    'ignore_errors' => true
                ]
            ]);

            $response = file_get_contents($bridgeUrl, false, $context);

            if ($response === false) {
                throw new \Exception("Bridge connection failed (no response within 120s)");
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['results'])) {
                throw new \Exception("Invalid bridge response: " . json_last_error_msg());
            }

            foreach ($data['results'] as $item) {
                $url = $item['url'] ?? 'unknown';
                $html = $item['html'] ?? null;
                $status = $item['status'] ?? 'error';
                $attempts = $item['attempts'] ?? 1;

                if ($status === 'success' && $html && strlen($html) > 2000) {
                    $len = strlen($html);
                    if ($len < 5000 || str_contains($html, 'awsWaf') || str_contains($html, 'captcha')) {
                        Log::warning("⚠️ Blocked content via bridge for $url ({$len} bytes)");
                        $results[$url] = null;
                    } else {
                        $results[$url] = $html;
                        $logMsg = $attempts > 1 ? "✅ Bridge fetched $url ({$len} bytes) after {$attempts} attempts" : "⚡ Bridge fetched $url ({$len} bytes)";
                        Log::info($logMsg);
                    }
                } else {
                    Log::warning("⚠️ Bridge failed for $url: " . ($item['error'] ?? 'Unknown') . " (Attempts: {$attempts})");
                    $results[$url] = null;
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error("🔥 Bridge failed: " . $e->getMessage() . ". Falling back to cURL or aborting.");
            // Возвращаем пустой массив или можно добавить фоллбек на обычный curl, если нужно
            return $results;
        }
    }

    public function getCurlParserInfo(array $urls): array
    {
        $results = [];
        $randomUa = $this->userAgents[array_rand($this->userAgents)];

        foreach ($urls as $url) {
            $url = trim($url);

            // Валидация URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Log::warning("Invalid URL skipped: $url");
                continue;
            }

            try {
                // Формируем параметры для парсера
                $params = http_build_query([
                    'url' => $url,
                    'userAgent' => $randomUa,
                    'width' => 1920,
                    'height' => 1080,
                    'locale' => 'en-US',
                    'timezone' => 'America/New_York',
                    'waitSelector' => 'h1[data-testid="hero__pageTitle"]',
                ]);

                $parserEndpoint = "http://spectrum_playwright_parser:3001/parse?$params";

                // Инициализируем cURL
                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL => $parserEndpoint,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Accept: text/html',
                        'Accept-Language: en-US,en;q=0.9',
                    ],
                    CURLOPT_TIMEOUT => 120,
                    CURLOPT_FOLLOWLOCATION => true,
                    // Не передаем User-Agent дважды, curl сам подставит, если нужно
                ]);

                $html = curl_exec($ch);
                $curlError = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);

                // Проверка ошибок cURL
                if ($curlError || $html === false) {
                    throw new \Exception("cURL error: $curlError");
                }

                // Проверка HTTP кода
                if ($httpCode !== 200) {
                    throw new \Exception("HTTP $httpCode from parser");
                }

                // Проверяем, не вернул ли парсер ошибку в JSON
                $decoded = json_decode($html, true);
                if (is_array($decoded) && isset($decoded['error'])) {
                    throw new \Exception("Parser error: " . $decoded['error']);
                }

                // Проверка на защиту или слишком короткий ответ
                $len = strlen($html);
                if ($len < 5000 || str_contains($html, 'awsWaf') || str_contains($html, 'captcha')) {
                    throw new \Exception("Blocked or short response ({$len} bytes)");
                }

                // ✅ Успех: сохраняем в формате [url => html]
                $results[$url] = $html;
                Log::info("✅ Parsed $url ({$len} bytes)");

            } catch (\Exception $e) {
                // Логируем ошибку, но НЕ прерываем цикл — обрабатываем остальные URL
                Log::error("Failed to parse $url: " . $e->getMessage(), ['url' => $url]);
                continue;
            }
        }

        return $results;
    }

}
