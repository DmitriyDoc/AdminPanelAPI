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
    private string $requiredStructureSelector = 'ul.ipc-metadata-list--dividers-between';

    private function getIdByType($params = [])
    {
        if (empty($this->urls)) {
            Log::info('getIdByType: No URLs to process.');
            return;
        }

        $connector = new CurlConnectorController();
        $arrayID = [];
        $stats = [
            'total_urls' => count($this->urls),
            'processed' => 0,
            'recovered' => 0,
            'failed_final' => 0
        ];

        Log::info("🚀 Starting parser for {$stats['total_urls']} URLs (max attempts: {$this->maxRetryAttempts})");

        // 🔄 Цикл попыток: 1-я основная + ретраи
        $urlsToProcess = $this->urls;

        for ($attempt = 1; $attempt <= $this->maxRetryAttempts && !empty($urlsToProcess); $attempt++) {
            $isFirstAttempt = ($attempt === 1);
            $currentBatchCount = count($urlsToProcess);

            if (!$isFirstAttempt) {
                // Экспоненциальная задержка: 3с, 6с, 9с...
                $delay = $this->retryDelaySec * $attempt;
                Log::info("⏳ Waiting {$delay}s before retry attempt #{$attempt} for {$currentBatchCount} URLs...");
                sleep($delay);
            }

            Log::debug("📥 Fetching batch (attempt #{$attempt}): {$currentBatchCount} URLs");
            $pages = $connector->getCurlImages($urlsToProcess);

            $nextRetryUrls = [];

            foreach ($pages as $url => $html) {
                $stats['processed']++;

                // Проверка качества контента
                if ($this->isValidHtml($html)) {
                    // ✅ Успех — парсим и собираем ID
                    $idsFromPage = $this->parseHtmlPage($html, $url);
                    $arrayID = array_merge($arrayID, $idsFromPage);

                    if (!$isFirstAttempt) {
                        $stats['recovered']++;
                        Log::info("✅ Recovered on attempt #{$attempt}: $url");
                    }
                } else {
                    // ❌ Неудача — добавляем в список на следующую попытку (если есть лимит)
                    if ($attempt < $this->maxRetryAttempts) {
                        $nextRetryUrls[] = $url;
                        Log::debug("⚠️ Failed attempt #{$attempt} for $url (will retry)");
                    } else {
                        $stats['failed_final']++;
                        Log::error("❌ Final failure for $url after {$this->maxRetryAttempts} attempts");
                    }
                }
            }

            // Подготовка к следующей итерации
            $urlsToProcess = $nextRetryUrls;
        }

        // Очистка исходного массива (как было в оригинале)
        $this->urls = [];

        // Уникализация и финальная статистика
        $arrayID = array_unique($arrayID);
        $collectedCount = count($arrayID);

        // 📊 Финальный отчёт
        Log::info("📈 Parsing Complete Summary:", [
            'total_requested' => $stats['total_urls'],
            'total_processed' => $stats['processed'],
            'recovered_after_retry' => $stats['recovered'],
            'failed_permanently' => $stats['failed_final'],
            'collected_unique_ids' => $collectedCount,
            'ids' => $arrayID
        ]);


        // Дальнейшая обработка (логика сохранена, закомментированное — как было)
        if (!empty($arrayID)) {
            if ($this->flagType) {
                Log::info("🎬 New movies for parsing", ['count' => $collectedCount]);
                foreach (array_chunk($arrayID, 5) as $chunk) {
                    foreach ($chunk as $id) {
//                        $existsInDb = DB::table('movies_info')->where('id_movie', $id)->exists();
//                        if ($existsInDb) {
//                            Log::debug("Movie already exists in DB, skipping", ['id' => $id]);
//                            continue;
//                        }
                        (new ParserUpdateMovieController)->parseMovies($params, $id);
                        Log::info("Write new movie ", ['id' => $id]);
                    }
                }
            } else {
                Log::info("🎭 New celebs for parsing", ['count' => $collectedCount]);
                foreach (array_chunk($arrayID, 5) as $chunk) {
                    foreach ($chunk as $id) {
                        (new ParserUpdateCelebController)->parseCelebs($this->typeImages, $id);
                        Log::info("Write new movie ", ['id' => $id]);
                    }
                }
            }
        }
    }

    private function isValidHtml(?string $html): bool
    {
        if ($html === null || !is_string($html)) {
            return false;
        }

        $len = strlen($html);
        if ($len < $this->minHtmlLength) {
            return false;
        }

        // Дополнительные проверки на блокировки
        if (str_contains($html, 'awsWaf') || str_contains($html, 'captcha') || str_contains($html, 'Access Denied')) {
            return false;
        }

        return true;
    }

    private function parseHtmlPage(string $html, string $url): array
    {
        $ids = [];

        try {
            $document = new Document($html, false);
        } catch (\Exception $e) {
            Log::error("❌ DOM parse error for $url: " . $e->getMessage());
            return $ids;
        }

        // Проверка наличия ключевой структуры
        if (!$document->has($this->requiredStructureSelector)) {
            Log::debug("⚠️ List structure not found on $url");
            return $ids;
        }

        $list = $document->find('div.ipc-metadata-list-summary-item__c');

        foreach ($list as $item) {
            // Пропускаем элементы с заглушками
            if ($item->has('div.ipc-media--fallback')) {
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

        return $ids;
    }
}
