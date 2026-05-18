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

    /**
     * Основной метод: маршрутизация запросов
     */
    public function getCurlImages(array $urls)
    {
        $results = [];
        $validUrls = array_filter(array_map('trim', $urls), fn($u) => filter_var($u, FILTER_VALIDATE_URL));
        if (empty($validUrls)) return $results;

        // 🔧 Разделяем: /title/ → Bridge, остальное → Playwright
        $titleUrls = array_filter($validUrls, fn($u) => preg_match('/\/title\/tt\d+\/$/', $u));
        $otherUrls = array_filter($validUrls, fn($u) => !preg_match('/\/title\/tt\d+\/$/', $u));

        // 1. Обрабатываем /title/ через Chromium Bridge (с ретраями)
        if (!empty($titleUrls)) {
            $bridgeResults = $this->fetchViaBridge(array_values($titleUrls));
            $results = array_merge($results, $bridgeResults);
        }

        // 2. Обрабатываем картинки через Playwright (постеры — с ретраями)
        if (!empty($otherUrls)) {
            $playwrightResults = $this->fetchViaPlaywright(array_values($otherUrls));
            $results = array_merge($results, $playwrightResults);
        }

        return $results;
    }

    /**
     * 🔧 Запрос к Chromium Bridge (для /title/) — с ретраями
     */
    private function fetchViaBridge(array $urls): array
    {
        $results = [];
        $bridgeUrl = 'http://spectrum_chromium_bridge:3002/fetch';

        $maxAttempts = 3;
        $baseDelay = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
//            if ($attempt > 1) {
//                $delay = min(pow($baseDelay, $attempt - 1), 15);
//                Log::info("⏳ Bridge Retry #{$attempt} for " . count($urls) . " URLs (wait {$delay}s)");
//                sleep($delay);
//            }
            $payload = [
                'urls' => $urls,
                'options' => [
                    'timeout' => 45000,
                    'concurrency' => 3,
                    'waitUntil' => 'networkidle2',
                    // 🔥 Кука для запоминания языка на IMDb
                    'cookies' => [
                        ['name' => 'lc-main', 'value' => 'ru_RU', 'domain' => '.imdb.com', 'path' => '/']
                    ]
                ]
            ];
            // === [RU-LOCALE-MOD] END ===
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                    'timeout' => 90,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($bridgeUrl, false, $context);
            if ($response === false) {
                Log::error("🔥 Bridge connection failed (attempt #{$attempt})");
                continue;
            }

            $data = json_decode($response, true);
            if (!isset($data['results'])) continue;

            $failedUrls = [];

            foreach ($data['results'] as $item) {
                $url = $item['url'];
                $html = $item['html'] ?? null;
                $status = $item['status'] ?? 'error';

                if ($status === 'success' && $html && strlen($html) > 10000) {
                    $results[$url] = $html;
                    if ($attempt > 1) Log::info("✅ Bridge recovered: $url");
                } else {
                    Log::warning("⚠️ Bridge failed for $url (attempt #{$attempt}): " . ($item['error'] ?? 'Unknown'));
                    $failedUrls[] = $url;
                }
            }

            if (empty($failedUrls) || $attempt == $maxAttempts) {
                foreach ($failedUrls as $fUrl) {
                    if (!isset($results[$fUrl])) $results[$fUrl] = null;
                }
                break;
            }

            $urls = $failedUrls;
        }

        return $results;
    }

    /**
     * 🔧 Запрос к Playwright Parser — с ретраями для постеров
     */
    private function fetchViaPlaywright(array $urls): array
    {
        $results = [];

        // Разделяем: постеры требуют ретраев, остальное — одна попытка
        $posterUrls = array_filter($urls, fn($u) => str_contains($u, 'contentTypes=poster'));
        $otherUrls = array_filter($urls, fn($u) => !str_contains($u, 'contentTypes=poster'));

        // 1. Постеры с ретраями
        if (!empty($posterUrls)) {
            $posterResults = $this->fetchPlaywrightWithRetry(array_values($posterUrls));
            $results = array_merge($results, $posterResults);
        }

        // 2. Остальное (still_frame, mediaviewer) — быстро, без ретраев
        if (!empty($otherUrls)) {
            $otherResults = $this->executeParallelFetch(array_values($otherUrls), false);
            $results = array_merge($results, $otherResults);
        }

        return $results;
    }

    /**
     * 🔧 Ретраи для Playwright (только для постеров)
     */
    private function fetchPlaywrightWithRetry(array $urls): array
    {
        $results = [];
        $maxAttempts = 6; // 🔧 Увеличили до 6 попыток для постеров
        $baseDelay = 3;   // 🔧 Базовая задержка 3 секунды

        for ($attempt = 1; $attempt <= $maxAttempts && !empty($urls); $attempt++) {
//            if ($attempt > 1) {
//                // 🔧 Экспоненциальная задержка: 3с, 9с, 27с... (макс. 30с)
//                $delay = min(pow($baseDelay, $attempt - 1), 30);
//                Log::info("⏳ Playwright Poster Retry #{$attempt} for " . count($urls) . " URLs (wait {$delay}s)");
//                sleep($delay);
//            }

            // 🔧 На поздних попытках увеличиваем таймаут до 120 сек
            $isLateAttempt = $attempt >= 3;
            $attemptResults = $this->executeParallelFetch($urls, true, $isLateAttempt);

            $failedUrls = [];

            foreach ($attemptResults as $url => $html) {
                // 🔧 Требования к контенту растут с каждой попыткой
                $minLen = $isLateAttempt ? 60000 : 30000;

                if ($html && strlen($html) > $minLen && !str_contains($html, 'awsWaf') && !str_contains($html, 'captcha')) {
                    $results[$url] = $html;
                    if ($attempt > 1) Log::info("✅ Playwright recovered poster: $url ({$minLen}+ bytes)");
                } else {
                    $len = $html ? strlen($html) : 0;
                    Log::warning("⚠️ Playwright poster failed: $url (attempt #{$attempt}, len: {$len}, min: {$minLen})");
                    if ($attempt < $maxAttempts) {
                        $failedUrls[] = $url;
                    } else {
                        $results[$url] = null;
                        Log::error("❌ Poster permanently failed for $url after {$maxAttempts} attempts");
                    }
                }
            }

            $urls = $failedUrls;
        }

        return $results;
    }

    /**
     * 🔧 Параллельный fetch через curl_multi (универсальный)
     */
    private function executeParallelFetch(array $urls, bool $isPosterBatch = false, bool $increaseTimeout = false): array
    {
        $results = [];
        if (empty($urls)) return $results;

        $multi = curl_multi_init();
        $channels = [];
        $urlMap = [];

        foreach ($urls as $url) {
            $randomUa = $this->userAgents[array_rand($this->userAgents)];
            $isMediaindex = str_contains($url, '/mediaindex?');

            // === [RU-LOCALE-MOD] START ===
            // Локаль для русского IMDb + заголовки с приоритетом ru
            $params = http_build_query([
                'url' => $url,
                'userAgent' => $randomUa,
                'width' => 1920,
                'height' => 1080,
                'locale' => 'ru-RU',              // 🔥 Русский язык
                'timezone' => 'Europe/Moscow',     // 🔥 РФ таймзона
                'waitSelector' => $isMediaindex ? 'section[data-testid="sub-section-images"]' : 'img[data-image-id$="-curr"]',
            ]);
            // === [RU-LOCALE-MOD] END ===

            $parserEndpoint = "http://spectrum_playwright_parser:3001/parse?$params";
            $ch = curl_init();

            // 🔧 Гибкий таймаут: 60 / 90 / 120 сек
            $timeout = $increaseTimeout ? 120 : ($isPosterBatch ? 90 : 60);

            // === [RU-LOCALE-MOD] START ===
            curl_setopt_array($ch, [
                CURLOPT_URL => $parserEndpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                // 🔥 Заголовок Accept-Language с приоритетом русского
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html',
                    'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
                ],
            ]);
            // === [RU-LOCALE-MOD] END ===
            curl_multi_add_handle($multi, $ch);
            $channels[(int)$ch] = $ch;
            $urlMap[(int)$ch] = $url;
        }

        // Выполняем мульти-запрос (код без изменений)
        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi, 0.3);

            while ($done = curl_multi_info_read($multi)) {
                $ch = $done['handle'];
                $url = $urlMap[(int)$ch] ?? 'unknown';
                $response = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);

                $html = null;
                if (!$curlError && $httpCode === 200 && $response && !str_contains($response, '"error"')) {
                    $html = $response;
                } else {
                    if ($curlError) Log::error("❌ Curl error for $url: $curlError");
                }

                $results[$url] = $html;

                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
                unset($channels[(int)$ch], $urlMap[(int)$ch]);
            }
        } while ($running > 0);

        curl_multi_close($multi);
        return $results;
    }

    /**
     * Парсинг информационных страниц (без изменений)
     */
    public function getCurlParserInfo(array $urls): array
    {
        $results = [];
        $randomUa = $this->userAgents[array_rand($this->userAgents)];

        foreach ($urls as $url) {
            $url = trim($url);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Log::warning("Invalid URL skipped: $url");
                continue;
            }

            try {
                // === [RU-LOCALE-MOD] START ===
                $params = http_build_query([
                    'url' => $url,
                    'userAgent' => $randomUa,
                    'width' => 1920,
                    'height' => 1080,
                    'locale' => 'ru-RU',
                    'timezone' => 'Europe/Moscow',
                    'waitSelector' => 'h1[data-testid="hero__pageTitle"]',
                ]);
                // === [RU-LOCALE-MOD] END ===
                $parserEndpoint = "http://spectrum_playwright_parser:3001/parse?$params";
                $ch = curl_init();
                // === [RU-LOCALE-MOD] START ===
                curl_setopt_array($ch, [
                    CURLOPT_URL => $parserEndpoint,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Accept: text/html',
                        'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',  // 🔥 RU first
                    ],
                    CURLOPT_TIMEOUT => 120,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);
                // === [RU-LOCALE-MOD] END ===
                $html = curl_exec($ch);
                $curlError = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($curlError || $html === false) {
                    throw new \Exception("cURL error: $curlError");
                }

                if ($httpCode !== 200) {
                    throw new \Exception("HTTP $httpCode from parser");
                }

                $decoded = json_decode($html, true);
                if (is_array($decoded) && isset($decoded['error'])) {
                    throw new \Exception("Parser error: " . $decoded['error']);
                }

                $len = strlen($html);
                $isWafBlocked = str_contains($html, 'awsWaf') || str_contains($html, 'Access Denied');
                $isCaptchaPage = str_contains($html, 'mp_verify') || (str_contains($html, 'captcha') && $len < 50000);

                if ($len < 5000 || $isWafBlocked || $isCaptchaPage) {
                    Log::warning("Validation failed for $url. Len: $len, WAF: " . ($isWafBlocked ? 'true' : 'false') . ", CaptchaPage: " . ($isCaptchaPage ? 'true' : 'false'));
                    throw new \Exception("Blocked or short response ({$len} bytes)");
                }

                $results[$url] = $html;
                Log::info("✅ Parsed $url ({$len} bytes)");

            } catch (\Exception $e) {
                Log::error("Failed to parse $url: " . $e->getMessage(), ['url' => $url]);
                continue;
            }
        }

        return $results;
    }

}
