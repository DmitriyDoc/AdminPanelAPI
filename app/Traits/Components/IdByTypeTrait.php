<?php

namespace App\Traits\Components;

use App\Http\Controllers\Parser\CurlConnectorController;
use App\Http\Controllers\Parser\ParserUpdateCelebController;
use App\Http\Controllers\Parser\ParserUpdateMovieController;
use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

trait IdByTypeTrait
{
    private int $minHtmlLength = 30000;
    private int $retryDelaySec = 3;
    private int $maxRetryAttempts = 3;
    private string $requiredStructureSelector = 'ul[class*="ipc-metadata-list--dividers"]';

    private function getIdByType($params = [])
    {
        if (empty($this->urls)) {
            Log::info('getIdByType: No URLs to process.');
            return;
        }

        $connector = new CurlConnectorController();
        $arrayID = [];
        $pagesHtml = [];
        $stats = [
            'total_urls' => count($this->urls),
            'processed' => 0,
            'recovered' => 0,
            'failed_final' => 0
        ];

        // 🔧 НОВОЕ: Сводная статистика по каждому URL
        $urlStats = [];

        Log::info("🚀 Starting parser for {$stats['total_urls']} URLs (max attempts: {$this->maxRetryAttempts})");

        $urlsToProcess = $this->urls;

        for ($attempt = 1; $attempt <= $this->maxRetryAttempts && !empty($urlsToProcess); $attempt++) {
            $isFirstAttempt = ($attempt === 1);
            $currentBatchCount = count($urlsToProcess);

            if (!$isFirstAttempt) {
                $delay = $this->retryDelaySec * $attempt;
                Log::info("⏳ Waiting {$delay}s before retry attempt #{$attempt} for {$currentBatchCount} URLs...");
                sleep($delay);
            }

            Log::debug("📥 Fetching batch (attempt #{$attempt}): {$currentBatchCount} URLs");

            $pages = $connector->fetchMovieInfo($urlsToProcess);

            $nextRetryUrls = [];

            foreach ($pages as $url => $html) {
                $stats['processed']++;

                if ($this->isValidHtml($html)) {
                    $pagesHtml[$url] = $html;
                    $idsFromPage = $this->parseHtmlPage($html, $url, $params);
                    $arrayID = array_merge($arrayID, $idsFromPage);

                    // 🔧 НОВОЕ: Логируем результаты по каждому URL
                    $urlStats[$url] = [
                        'status' => 'success',
                        'collected_count' => count($idsFromPage),
                        'ids' => $idsFromPage,
                        'attempt' => $attempt,
                    ];

                    if (!$isFirstAttempt) {
                        $stats['recovered']++;
                        Log::info("✅ Recovered on attempt #{$attempt}: $url");
                    }
                } else {
                    // 🔧 НОВОЕ: Логируем неудачные URL
                    $urlStats[$url] = [
                        'status' => 'failed',
                        'collected_count' => 0,
                        'ids' => [],
                        'attempt' => $attempt,
                    ];

                    if ($attempt < $this->maxRetryAttempts) {
                        $nextRetryUrls[] = $url;
                        Log::debug("⚠️ Failed attempt #{$attempt} for $url (will retry)");
                    } else {
                        $stats['failed_final']++;
                        Log::error("❌ Final failure for $url after {$this->maxRetryAttempts} attempts");
                    }
                }
            }

            $urlsToProcess = $nextRetryUrls;
        }

        $this->urls = [];
        $arrayID = array_unique($arrayID);
        $collectedCount = count($arrayID);

        Log::info("📈 Parsing Complete Summary:", [
            'total_requested' => $stats['total_urls'],
            'total_processed' => $stats['processed'],
            'recovered_after_retry' => $stats['recovered'],
            'failed_permanently' => $stats['failed_final'],
            'collected_unique_ids' => $collectedCount,
        ]);
        if ($collectedCount > 0) {
            Log::info("🆔 Collected IDs:", ['ids' => $arrayID]);
        }

        // 🔧 Если это парсинг чарта, сохраняем в БД
        if (!empty($params['is_chart'])) {
            Log::info("📊 Chart mode detected, saving to database...");

            foreach ($pagesHtml as $chartUrl => $html) {
                if (str_contains($chartUrl, '/chart/')) {
                    $this->parseAndSaveChart($chartUrl, $html, $arrayID);
                }
            }
        }

        // 🔧 Обработка найденных фильмов с паузами (как в очереди)
        if (empty($params['is_chart'])) {
            $this->processCollectedIds($arrayID, $params);
        }
    }

    private function processCollectedIds(array $arrayID, array $params)
    {
        if (empty($arrayID)) return;

        $collectedCount = count($arrayID);

        if ($this->flagType) {
            Log::info("🎬 New movies for parsing", ['count' => $collectedCount]);

            foreach (array_chunk($arrayID, 5) as $chunk) {
                foreach ($chunk as $id) {
                    $delay = random_int(self::DELAY_BEFORE_MOVIE_PARSE_MIN, self::DELAY_BEFORE_MOVIE_PARSE_MAX);
                    if ($delay > 0) {
                        //Log::debug("⏱️ Delaying parse for movie {$id} by {$delay}s");
                        sleep($delay);
                    }

                    (new \App\Http\Controllers\Parser\ParserUpdateMovieController)->parseMovies($params, $id);
                    Log::info("✅ Written new movie", ['id' => $id]);
                }
                usleep(200000);
            }
        } else {
            Log::info("🎭 New celebs for parsing", ['count' => $collectedCount]);
            foreach (array_chunk($arrayID, 5) as $chunk) {
                foreach ($chunk as $id) {
                    $delay = random_int(self::DELAY_BEFORE_MOVIE_PARSE_MIN, self::DELAY_BEFORE_MOVIE_PARSE_MAX);
                    if ($delay > 0) sleep($delay);

                    (new \App\Http\Controllers\Parser\ParserUpdateCelebController)->parseCelebs($this->typeImages, $id);
                    Log::info("✅ Written new celeb", ['id' => $id]);
                }
                usleep(200000);
            }
        }
    }

    private function isValidHtml(?string $html): bool
    {
        if ($html === null || !is_string($html)) {
            Log::debug("❌ isValidHtml failed: HTML is null or not string");
            return false;
        }

        $len = strlen($html);

        if ($len < $this->minHtmlLength) {
            Log::debug("❌ isValidHtml failed: length {$len} is too short");
            return false;
        }

        if ($len > 100000) {
            return true;
        }

        if (str_contains($html, 'Access Denied') ||
            str_contains($html, '<title>403 Forbidden</title>') ||
            preg_match('/<title>Just a moment/i', $html)) {
            Log::debug("❌ isValidHtml failed: Blocked/WAF detected on small page");
            return false;
        }

        return true;
    }

    private function parseHtmlPage(string $html, string $url, array $params = []): array
    {
        $ids = [];
        $isChartMode = !empty($params['is_chart']);

        try {
            $document = new Document($html, false);
        } catch (\Exception $e) {
            Log::error("❌ DOM parse error for $url: " . $e->getMessage());
            return $ids;
        }

        if (!$document->has($this->requiredStructureSelector)) {
            Log::debug("⚠️ List structure not found on $url");
            return $ids;
        }

        $list = $document->find('div.ipc-metadata-list-summary-item__c');
        $skippedNoPoster = 0;

        foreach ($list as $item) {
            // 🔧 Для CHARTS — парсим ВСЕ (включая без постера)
            // 🔧 Для MOVIES — пропускаем элементы БЕЗ постера
            if (!$isChartMode && $item->has('div.ipc-media--fallback')) {
                $skippedNoPoster++;
                continue;
            }

            $titleElements = $item->find('a.ipc-title-link-wrapper');
            if (empty($titleElements)) {
                continue;
            }

            $href = $titleElements[0]->attr('href');

            if ($this->flagType) {
                // 🎬 Фильмы
                if ($idMovie = get_id_from_url($href, self::ID_PATTERN)) {
                    $ids[] = $idMovie;
                }
            } else {
                // 🎭 Актёры / персоны
                if ($idActor = get_id_from_url($href, self::ACTOR_PATTERN)) {
                    if (!$this->flagNewUpdate) {
                        if (!$this->checkExists('celebs_info', $idActor)) {
                            $ids[] = $idActor;
                        }
                    } else {
                        $ids[] = $idActor;
                    }
                }
            }
        }

        // 🔧 НОВОЕ: Детальный лог по каждому URL сразу после парсинга
        Log::info("🔍 Parsed URL", [
            'url' => $url,
            'mode' => $isChartMode ? 'chart' : ($this->flagType ? 'movies' : 'celebs'),
            'collected_ids_count' => count($ids),
            'collected_ids' => $ids,
            'skipped_no_poster' => $skippedNoPoster,
            'total_elements' => count($list),
        ]);

        return $ids;
    }
}
