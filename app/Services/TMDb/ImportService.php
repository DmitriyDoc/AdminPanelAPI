<?php

namespace App\Services\TMDb;

use App\Jobs\TmdbMovieUpdateJob;
use App\Jobs\TmdbCelebUpdateJob;
use App\Models\MovieInfo;
use App\Models\CelebsInfo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ImportService extends BaseTmdbService
{
    // ... весь остальной код без изменений ...
    /**
     * Точечный импорт одной сущности (Фильм, Сериал или Актер)
     */
    public function importSingle(string $inputId, string $entityType): array
    {
        $inputId = trim($inputId);
        $isImdb = str_starts_with(strtolower($inputId), 'tt') || str_starts_with(strtolower($inputId), 'nm');

        $tmdbId = null;
        $imdbId = null;
        $mediaType = null;

        try {
            if ($isImdb) {
                $imdbId = strtolower($inputId);
                if (str_starts_with($imdbId, 'tt')) {
                    $findResponse = $this->makeRequest("/find/{$imdbId}", ['external_source' => 'imdb_id']);
                    if (!empty($findResponse['movie_results'])) {
                        $tmdbId = $findResponse['movie_results'][0]['id'];
                        $mediaType = 'movie';
                    } elseif (!empty($findResponse['tv_results'])) {
                        $tmdbId = $findResponse['tv_results'][0]['id'];
                        $mediaType = 'tv';
                    }
                } elseif (str_starts_with($imdbId, 'nm')) {
                    $findResponse = $this->makeRequest("/find/{$imdbId}", ['external_source' => 'imdb_id']);
                    if (!empty($findResponse['person_results'])) {
                        $tmdbId = $findResponse['person_results'][0]['id'];
                        $mediaType = 'person';
                    }
                }
            } else {
                $tmdbId = (int) $inputId;
                if ($entityType === 'person') {
                    $extIds = $this->makeRequest("/person/{$tmdbId}/external_ids");
                    $imdbId = $extIds['imdb_id'] ?? null;
                    $mediaType = 'person';
                } else {
                    $extIds = $this->makeRequest("/{$entityType}/{$tmdbId}/external_ids");
                    $imdbId = $extIds['imdb_id'] ?? null;
                    $mediaType = $entityType;
                }
            }

            if (!$imdbId || !$tmdbId || !$mediaType) {
                return ['success' => false, 'message' => 'Не удалось сопоставить ID. Проверьте правильность ввода.'];
            }

            if ($mediaType === 'person') {
                $exists = CelebsInfo::where('id_celeb', $imdbId)->exists();
            } else {
                $exists = MovieInfo::where('id_movie', $imdbId)->exists();
            }

            if ($exists) {
                return ['success' => false, 'message' => 'Эта сущность уже существует в базе данных.'];
            }

            // Постановка в очередь
            if ($mediaType === 'person') {
                TmdbCelebUpdateJob::dispatch([
                    'id' => $imdbId,
                    'typeSync' => 'import_sync'
                ])->onQueue('parsing_celeb');
            } else {
                $internalType = 'FeatureFilm'; // По умолчанию для movie

                if ($mediaType === 'tv') {
                    $tvDetails = $this->makeRequest("/tv/{$tmdbId}");
                    $tvType = $tvDetails['type'] ?? null;
                    $seasons = $tvDetails['number_of_seasons'] ?? 0;

                    if ($tvType === 'Miniseries' || ($tvType === null && $seasons <= 1)) {
                        $internalType = 'MiniSeries';
                    } else {
                        $internalType = 'TvSeries';
                    }
                }

                TmdbMovieUpdateJob::dispatch([
                    'id' => $imdbId,
                    'typeSync' => 'import_sync',
                    'posterType' => 'poster'
                ], $internalType)->onQueue('parsing_movie');
            }

            return ['success' => true, 'message' => 'Импорт успешно поставлен в очередь.'];

        } catch (\Exception $e) {
            Log::error("Import Single Failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Ошибка при обращении к API TMDB.'];
        }
    }
    /**
     * Получить доступные каталоги для импорта
     */
    public function getAvailableLists(): array
    {
        return [
            'movie' => [
                ['value' => 'popular', 'label' => 'Популярные фильмы'],
                ['value' => 'upcoming', 'label' => 'Скоро в кино'],
                ['value' => 'top_rated', 'label' => 'Высокий рейтинг'],
            ],
            'person' => [
                ['value' => 'popular', 'label' => 'Популярные персоны'],
            ]
        ];
    }

    /**
     * Получить элементы из каталога с проверкой наличия в БД
     */
    public function browseList(string $entityType, string $listType, int $page = 1): array
    {
        $endpoint = '';
        $isMovie = in_array($entityType, ['movie', 'tv']);

        if ($entityType === 'movie') {
            $endpoint = "/movie/{$listType}";
        } elseif ($entityType === 'tv') {
            $endpoint = "/tv/{$listType}";
        } elseif ($entityType === 'person') {
            $endpoint = "/person/{$listType}";
        } else {
            return ['items' => [], 'total_pages' => 0];
        }

        try {
            $response = $this->makeRequest($endpoint, ['page' => $page]);
            $results = $response['results'] ?? [];

            // Собираем все IMDb ID для массовой проверки в БД
            $imdbIdsToCheck = [];
            $tmdbToImdbMap = [];

            // Для фильмов/сериалов нам нужно сначала получить их external_ids,
            // но чтобы не делать N запросов, мы проверим наличие по TMDB ID, если у нас есть маппинг,
            // ИЛИ сделаем пакетный запрос.
            // УПРОЩЕННЫЙ ВАРИАНТ: проверяем наличие по TMDB ID в нашей таблице (если мы храним его),
            // НО так как мы храним только imdb_id, нам придется сделать запрос к external_ids для каждого.
            // Чтобы не делать N запросов к API, мы проверим наличие в БД по тем ID, которые УЖЕ знаем,
            // а для новых просто пометим как "requires_check".
            // ЛУЧШИЙ ВАРИАНТ: проверять наличие прямо здесь через пакетный запрос к external_ids.

            $items = [];
            $tmdbIds = array_column($results, 'id');

            // Получаем IMDb ID для всех элементов страницы (пакетно, чанками по 20)
            $imdbMap = $this->getImdbIdsForList($tmdbIds, $entityType);

            $existingImdbIds = [];
            $validImdbIds = [];

            foreach ($imdbMap as $tmdbId => $imdbId) {
                if ($imdbId) {
                    $validImdbIds[] = $imdbId;
                }
            }


            $existingMap = [];

            if ($entityType === 'person') {
                $existing = CelebsInfo::whereIn('id_celeb', $validImdbIds)->pluck('id_celeb')->toArray();
                foreach ($existing as $id) {
                    $existingMap[$id] = ['exists' => true, 'published' => null];
                }
            } else {
                $existing = MovieInfo::whereIn('id_movie', $validImdbIds)
                    ->select('id_movie', 'published')
                    ->get()
                    ->keyBy('id_movie');

                foreach ($existing as $movie) {
                    $existingMap[$movie->id_movie] = [
                        'exists' => true,
                        'published' => (int)$movie->published
                    ];
                }
            }

            foreach ($results as $item) {
                $tmdbId = $item['id'];
                $imdbId = $imdbMap[$tmdbId] ?? null;

                $dbInfo = $imdbId && isset($existingMap[$imdbId]) ? $existingMap[$imdbId] : null;
                $existsInDb = $dbInfo ? true : false;
                $publishedStatus = $dbInfo ? $dbInfo['published'] : null;

                $imagePath = $item['poster_path'] ?? $item['profile_path'] ?? null;
                $imageUrl = $imagePath ? env('TMDB_IMAGE_BASE_URL')."/t/p/w200{$imagePath}" : null;

                $releaseDate = $item['release_date'] ?? $item['first_air_date'] ?? null;
                $popularity = $item['popularity'] ?? null;

                $items[] = [
                    'tmdb_id' => $tmdbId,
                    'imdb_id' => $imdbId,
                    'title' => $item['title'] ?? $item['name'] ?? 'Unknown',
                    'poster_path' => $imageUrl,
                    'release_date' => $releaseDate,
                    'popularity' => $popularity,
                    'exists_in_db' => $existsInDb,
                    'published_status' => $publishedStatus,
                    'entity_type' => $entityType,
                ];
            }

            return [
                'items' => $items,
                'total_pages' => $response['total_pages'] ?? 1,
                'total_results' => $response['total_results'] ?? 0,
                'current_page' => $page,
            ];

        } catch (\Exception $e) {
            Log::error("Browse List Failed: " . $e->getMessage());
            return ['items' => [], 'total_pages' => 0];
        }
    }

    /**
     * Пакетное получение IMDb ID для списка (чтобы избежать N+1 запросов к API)
     */
    private function getImdbIdsForList(array $tmdbIds, string $entityType): array
    {
        $map = [];
        $chunkedIds = array_chunk($tmdbIds, 20);

        foreach ($chunkedIds as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $entityType) {
                foreach ($chunk as $id) {
                    $endpoint = $entityType === 'person' ? "/person/{$id}/external_ids" : "/{$entityType}/{$id}/external_ids";
                    $pool->as((string)$id)->get($this->baseUrl . "{$endpoint}?api_key={$this->apiKey}");
                }
            });

            foreach ($responses as $tmdbId => $response) {
                if ($response instanceof \Throwable || !$response->successful()) {
                    continue;
                }
                $data = $response->json();
                $map[$tmdbId] = $data['imdb_id'] ?? null;
            }

            // Небольшая задержка между чанками
            usleep(300000);
        }

        return $map;
    }

    /**
     * Массовый импорт выбранных элементов
     */
    public function importBatch(array $items): array
    {
        $queuedCount = 0;
        $skippedCount = 0;

        foreach ($items as $item) {
            $imdbId = $item['imdb_id'] ?? null;
            $entityType = $item['entity_type'] ?? 'movie';

            if (!$imdbId) {
                $skippedCount++;
                continue;
            }

            // Проверка на существование (двойная защита)
            $exists = false;
            if ($entityType === 'person') {
                $exists = CelebsInfo::where('id_celeb', $imdbId)->exists();
            } else {
                $exists = MovieInfo::where('id_movie', $imdbId)->exists();
            }

            if ($exists) {
                $skippedCount++;
                continue;
            }

            // Постановка в очередь
            if ($entityType === 'person') {
                TmdbCelebUpdateJob::dispatch([
                    'id' => $imdbId,
                    'typeSync' => 'import_sync'
                ])->onQueue('parsing_celeb');
            } else {
                // Для простоты массового импорта сериалов пока отправляем как TvSeries,
                // а ImportService внутри джобы или при обработке сам уточнит тип,
                // либо мы можем определить тип заранее.
                // Упростим: отправляем тип, который пришел с фронта, или дефолтный.
                $internalType = ($entityType === 'tv') ? 'TvSeries' : 'FeatureFilm';

                TmdbMovieUpdateJob::dispatch([
                    'id' => $imdbId,
                    'typeSync' => 'import_sync',
                    'posterType' => 'poster'
                ], $internalType)->onQueue('parsing_movie');
            }

            $queuedCount++;
        }

        return [
            'success' => true,
            'queued' => $queuedCount,
            'skipped' => $skippedCount,
            'message' => "Поставлено в очередь: {$queuedCount}, Пропущено (уже есть): {$skippedCount}"
        ];
    }
}
