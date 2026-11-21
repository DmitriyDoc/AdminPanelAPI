<?php

namespace App\Http\Controllers\Parser;

use Illuminate\Support\Facades\Log;

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

    private $parserBaseUrl = 'http://spectrum_playwright_parser:3001';

    /**
     * 1. Получение основной информации о фильме (/title/tt.../)
     */
    public function fetchMovieInfo(array $urls): array
    {
        return $this->dispatchRequests('/parse/info', $urls, 90);
    }

    /**
     * 2. Получение информации и кредитов актера (/name/nm.../)
     */
    public function fetchCelebCredits(array $urls): array
    {
        return $this->dispatchRequests('/parse/credits', $urls, 90);
    }

    /**
     * 3. Получение страниц индексов для сбора ID (/mediaindex?contentTypes=...)
     */
    public function fetchMediaIds(array $urls): array
    {
        return $this->dispatchRequests('/parse/ids', $urls, 60);
    }

    /**
     * 4. Получение содержимого viewer для ПОСТЕРОВ (/mediaviewer/rm...)
     */
    public function fetchViewerPoster(array $urls): array
    {
        return $this->dispatchRequests('/parse/viewer-poster', $urls, 60);
    }

    /**
     * 5. Получение содержимого viewer для КАДРОВ (/mediaviewer/rm...)
     */
    public function fetchViewerImage(array $urls): array
    {
        return $this->dispatchRequests('/parse/viewer-image', $urls, 60);
    }

    /**
     * Универсальный диспетчер запросов к Node.js
     */
    private function dispatchRequests(string $endpoint, array $urls, int $timeout): array
    {
        $results = [];
        $validUrls = array_filter(array_map('trim', $urls), fn($u) => filter_var($u, FILTER_VALIDATE_URL));

        foreach ($validUrls as $url) {
            $randomUa = $this->userAgents[array_rand($this->userAgents)];

            // Node.js теперь принимает POST с JSON телом
            $payload = json_encode([
                'url' => $url,
                'userAgent' => $randomUa,
                'locale' => 'ru-RU',
                'timezone' => 'Europe/Moscow',
            ], JSON_UNESCAPED_SLASHES);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n" .
                        "Accept: application/json\r\n" .
                        "Accept-Language: ru-RU,ru;q=0.9,en;q=0.8\r\n",
                    'content' => $payload,
                    'timeout' => $timeout,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($this->parserBaseUrl . $endpoint, false, $context);

            // Микро-пауза между запросами для имитации человека и защиты от rate-limit
            usleep(200000); // 0.2 секунды

            if ($response === false) {
                Log::warning("⚠️ Connection failed for $endpoint: $url");
                $results[$url] = null;
                continue;
            }

            $data = json_decode($response, true);

            if (is_array($data) && isset($data['status']) && $data['status'] === 'success' && !empty($data['html'])) {
                $results[$url] = $data['html'];
            } else {
                $errorMsg = $data['error'] ?? 'Unknown parser error';
                Log::debug("⚠️ Parser failed for $url ($endpoint): $errorMsg");
                $results[$url] = null;
            }
        }

        return $results;
    }
}
