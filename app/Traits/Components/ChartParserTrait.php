<?php

namespace App\Traits\Components;

use App\Http\Controllers\TranslatorController;
use App\Models\ImdbChart;
use App\Models\ImdbChartMovie;
use DiDom\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait ChartParserTrait
{
    /**
     * Парсинг и сохранение IMDb Chart
     */
    protected function parseAndSaveChart(string $url, string $html, array $movieIds): void
    {
        // Извлекаем chart_id из URL: /chart/titles/in0000001/
        if (!preg_match('/\/chart\/titles\/(in\d+)\/?/', $url, $matches)) {
            Log::error("❌ Cannot extract chart_id from URL: $url");
            return;
        }

        $chartId = $matches[1]; // in0000001

        Log::info("📊 Processing chart", ['chart_id' => $chartId, 'movies' => count($movieIds)]);

        try {
            $document = new Document($html, false);

            // Ищем заголовок H1
            $h1Element = $document->first('h1.chart-layout-specific-title-text');
            if (!$h1Element) {
                $h1Element = $document->first('h1');
            }

            if (!$h1Element) {
                Log::warning("⚠️ H1 title not found for chart: $chartId");
                $titleEn = "Unknown Chart $chartId";
            } else {
                $titleEn = trim($h1Element->text());
                Log::info("📝 Chart title extracted", ['chart_id' => $chartId, 'title' => $titleEn]);
            }

            // Переводим заголовок
            $titleRu = $this->translateChartTitle($titleEn);

            // Сохраняем в БД с использованием транзакции
            DB::transaction(function () use ($chartId, $titleEn, $titleRu, $url, $movieIds) {
                // Создаем или обновляем список
                $chart = ImdbChart::updateOrCreate(
                    ['chart_id' => $chartId],
                    [
                        'title_en' => $titleEn,
                        'title_ru' => $titleRu,
                        'url' => $url,
                        'movies_count' => count($movieIds),
                        'last_parsed_at' => now(),
                    ]
                );

                Log::info("✅ Chart saved/updated", ['chart_id' => $chartId, 'id' => $chart->id]);

                // Удаляем старые связи для этого списка
                ImdbChartMovie::where('chart_id', $chartId)->delete();

                // Создаем новые связи с позициями
                $chartMovies = [];
                foreach ($movieIds as $position => $movieId) {
                    $chartMovies[] = [
                        'chart_id' => $chartId,
                        'movie_id' => $movieId,
                        'position' => $position + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Массовая вставка для производительности
                if (!empty($chartMovies)) {
                    ImdbChartMovie::insert($chartMovies);
                    Log::info("✅ Chart movies saved", ['chart_id' => $chartId, 'count' => count($chartMovies)]);
                }
            });

        } catch (\Exception $e) {
            Log::error("❌ Error parsing chart: " . $e->getMessage(), ['url' => $url]);
        }
    }

    /**
     * Перевод заголовка списка
     */
    protected function translateChartTitle(string $titleEn): ?string
    {
        if (empty($titleEn)) {
            return null;
        }

        // Проверяем, не переведено ли уже
        if (preg_match('/[\p{Cyrillic}]/u', $titleEn)) {
            return $titleEn;
        }

        try {
            $localizing = new TranslatorController();
            $translated = $localizing->titleTranslate($titleEn);

            if ($translated && $translated !== $titleEn) {
                Log::info("🌐 Chart title translated", ['en' => $titleEn, 'ru' => $translated]);
                return $translated;
            }

            return $titleEn;
        } catch (\Exception $e) {
            Log::warning("⚠️ Failed to translate chart title: " . $e->getMessage());
            return $titleEn;
        }
    }

    /**
     * Проверка и парсинг отсутствующих фильмов из чарта
     */
    protected function checkAndParseMissingMovies(string $chartId): void
    {

        $chart = ImdbChart::where('chart_id', $chartId)->first();
        if (!$chart) {
            Log::warning("⚠️ Chart not found: $chartId");
            return;
        }

        $movieIds = $chart->getMovieIds();
        $missingIds = [];

        foreach ($movieIds as $movieId) {
            // Проверяем наличие в основной таблице фильмов
            $exists = DB::table('localizing_info_movies_en')
                ->where('id_movie', $movieId)
                ->exists();

            if (!$exists) {
                $missingIds[] = $movieId;
            }
        }

        if (empty($missingIds)) {
            Log::info("✅ All movies from chart $chartId are already in DB");
            return;
        }

        Log::info("🔍 Found missing movies", ['chart_id' => $chartId, 'count' => count($missingIds)]);

        // Парсим отсутствующие фильмы
        foreach (array_chunk($missingIds, 5) as $chunk) {
            foreach ($chunk as $movieId) {
                $delay = random_int(1, 3);
                sleep($delay);

                (new \App\Http\Controllers\Parser\ParserUpdateMovieController)->parseMovies([
                    'segment' => 'chart_missing',
                    'typeImages' => 'still_frame',
                    'typePosters' => 'poster',
                ], $movieId);

                Log::info("✅ Parsed missing movie", ['movie_id' => $movieId]);
            }
        }
    }
}
