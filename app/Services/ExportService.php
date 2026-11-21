<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use App\Models\MovieCategory;
use App\Models\MovieInfo;
use App\Models\Tag;
use App\Models\TagsMoviesPivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Http\Client\Response;
class ExportService
{
    public function exportCollections()
    {
        $collectionsDir = 'collections';
        $collectionsDirs = Storage::disk('public')->directories($collectionsDir);
        $chunkSize = 25;

        if (empty($collectionsDirs)) {
            Log::error('No collection directories found in storage/public/collections');
            return response()->json(['success' => false, 'message' => 'No collections directories found'], 422);
        }

        $allFiles = [];

        foreach ($collectionsDirs as $colDir) {
            $collectionId = basename($colDir);

            $subDirs = [
                'coverCol' => "collections/{$collectionId}/cover",
            ];

            foreach ($subDirs as $field => $path) {
                $files = Storage::disk('public')->files($path);
                if (empty($files)) {
                    Log::warning("No files found in {$path} for collection {$collectionId}");
                    continue;
                }

                foreach ($files as $file) {
                    $filePath = Storage::disk('public')->path($file);
                    if (!is_readable($filePath)) {
                        Log::error("File is not readable: {$filePath}");
                        continue;
                    }

                    $filename = basename($file);
                    $allFiles[] = [
                        'path' => $filePath,
                        'name' => "collections[{$collectionId}][{$field}][]",
                        'filename' => $filename,
                    ];
                }
            }
        }

        if (empty($allFiles)) {
            Log::error('No files to export');
            return response()->json(['success' => false, 'message' => 'No files to export'], 422);
        }

        Log::info('Total files to export:', ['count' => count($allFiles)]);

        // Разбиваем на чанки
        $chunks = array_chunk($allFiles, $chunkSize);

        $successfulChunks = 0;
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $formData = [];
            foreach ($chunk as $item) {
                $contents = file_get_contents($item['path']);
                if ($contents === false) {
                    Log::error("Failed to read file: {$item['path']}");
                    continue;
                }

                $formData[] = [
                    'name' => $item['name'],
                    'contents' => $contents,
                    'filename' => $item['filename'],
                ];
            }

            if (empty($formData)) {
                Log::warning("Chunk" . $index + 1 ."has no valid files, skipping.");
                continue;
            }

            try {
                $response = Http::withToken(config('services.kinospectr.api_token'))
                    ->asMultipart()
                    ->timeout(300)
                    ->post(config('services.kinospectr.api_url') . '/api/collections/import', $formData);

                if ($response->successful()) {
                    Log::info("Chunk" . $index + 1 ."imported successfully");
                    $successfulChunks++;
                } else {
                    Log::error("Chunk" . $index + 1 ." failed", [
                        'status' => $response->status(),
                        'response' => $response->json(),
                        'chunk_size' => count($formData),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Chunk " . $index + 1 ." threw exception", [
                    'exception' => $e->getMessage(),
                    'chunk_size' => count($formData),
                ]);
            }

            unset($formData, $response);
            gc_collect_cycles();
        }

        if ($successfulChunks === $totalChunks && $totalChunks > 0) {
            return response()->json([
                'success' => true,
                'type' => 'success',
                'message' => "All {$totalChunks} chunks imported successfully."
            ]);
        } elseif ($successfulChunks > 0) {
            return response()->json([
                'success' => false,
                'type' => 'partial',
                'message' => "Partially imported: {$successfulChunks}/{$totalChunks} chunks succeeded."
            ], 207);
        } else {
            return response()->json([
                'success' => false,
                'type' => 'error',
                'message' => 'All chunks failed to import.'
            ], 500);
        }
    }
    public function exportIndex()
    {
        $sectionsDir = 'sections';
        $sectionDirs = Storage::disk('public')->directories($sectionsDir);
        $formData = [];

        if (empty($sectionDirs)) {
            Log::error('No section directories found in storage/public/sections');
            return response()->json(['success' => false, 'message' => 'No section directories found'], 422);
        }

        $sectionIds = array_map('basename', $sectionDirs);

        foreach ($sectionDirs as $sectionDir) {
            $sectionId = basename($sectionDir);
            $subDirs = [
                'section_logo' => "sections/{$sectionId}/section_logo",
                'backdrop' => "sections/{$sectionId}/backdrop",
                'film_logo_ru' => "sections/{$sectionId}/film_logos/ru",
                'film_logo_en' => "sections/{$sectionId}/film_logos/en",
                'posters' => "sections/{$sectionId}/posters",
            ];

            foreach ($subDirs as $field => $path) {
                $files = Storage::disk('public')->files($path);
                if (empty($files)) continue;

                foreach ($files as $file) {
                    $filePath = Storage::disk('public')->path($file);
                    if (!is_readable($filePath)) continue;

                    $originalFilename = basename($file);

                    $formData[] = [
                        'name' => "sections[{$sectionId}][{$field}][]",
                        'contents' => file_get_contents($filePath),
                        'filename' => $originalFilename, // Возвращаем оригинальное имя
                    ];
                }
            }
        }

        // Получаем категории
        $categories = Category::whereIn('id', $sectionIds)->get(['id', 'index_id', 'value', 'title_en', 'title_ru'])->toArray();
        foreach ($categories as $index => $category) {
            foreach ($category as $key => $value) {
                $formData[] = [
                    'name' => "categories[{$index}][{$key}]",
                    'contents' => $value,
                ];
            }
        }

        if (empty($formData)) {
            Log::error('No files to export');
            return response()->json(['success' => false, 'message' => 'No files to export'], 422);
        }

        try {
            $response = Http::withToken(config('services.kinospectr.api_token'))
                ->asMultipart()
                ->post(config('services.kinospectr.api_url') . '/api/sections/import', $formData);

            if ($response->successful()) {
                return response()->json(['success' => true, 'type' => 'success', 'message' => $response->json()['message'] ]);
            } else {
                Log::error('Failed to export sections', ['response' => $response->json()]);
                return response()->json(['success' => false,  'type' => 'error','message' => 'Failed to export sections: ' . ($response->json()['message'] ?? 'Unknown error')], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error exporting sections: ' . $e->getMessage());
            return response()->json(['success' => false, 'type' => 'error', 'message' => 'Error exporting sections: ' . $e->getMessage()], 500);
        }
    }
    public function exportMovies(bool $isFullSync = false): array
    {
        if ($isFullSync) {
            set_time_limit(600);
        }

        $statusMovie = $isFullSync ? 2 : 1;
        $dbChunkSize = $isFullSync ? 100 : 20;
        $apiChunkSize = 50;
        $maxRetries = 3;

        // Получаем общее заявленное количество заранее для итогового отчета
        $totalPendingCount = MovieInfo::where('published', $statusMovie)->count();

        if ($totalPendingCount === 0) {
            return [
                'success' => false,
                'message' => $isFullSync ? 'Нет опубликованных фильмов для синхронизации' : 'No movies found',
                'type' => 'warning',
                'status' => 200
            ];
        }

        $totalProcessed = 0;
        $totalSkipped = 0;
        $validationErrorsLog = [];

        // chunkById безопасно выгружает данные порциями по ID, не перегружая оперативную память
        MovieInfo::where('published', $statusMovie)
            ->with(['localazingRu', 'localazingEn'])
            ->orderBy('id') // Обязательно для chunkById
            ->chunkById($dbChunkSize, function ($movies) use (&$totalProcessed, &$totalSkipped, &$validationErrorsLog, $apiChunkSize, $maxRetries, $isFullSync) {

                $validMoviesData = [];

                // 1. Валидация текущей порции из БД
                foreach ($movies as $movie) {
                    $mappedData = $this->mapMovieToKinospectrFormat($movie);

                    if ($mappedData === null) {
                        $totalSkipped++;
                        $validationErrorsLog[] = "Movie ID {$movie->id} (IMDB: {$movie->id_movie}): Critical mapping error.";
                        continue;
                    }

                    $errors = $this->validateMovieData($mappedData);
                    if (!empty($errors)) {
                        $totalSkipped++;
                        $validationErrorsLog[] = "Movie ID {$movie->id} (IMDB: {$movie->id_movie}): " . implode('; ', $errors);
                        continue;
                    }

                    $validMoviesData[] = $mappedData;
                }

                if (empty($validMoviesData)) {
                    if (!$isFullSync) {
                        return false; // Останавливаем цикл для штатного режима, если нет валидных
                    }
                    return; // Переходим к следующему чанку БД при полной синхронизации
                }

                // 2. Получаем связи категорий только для валидных фильмов текущего чанка
                $imdbIdsToExport = array_column($validMoviesData, 'imdb_id');
                $movieCategoriesLinks = MovieCategory::whereIn('id_movie', $imdbIdsToExport)
                    ->get(['id_movie', 'id_category'])
                    ->map(fn($mc) => ['imdb_id' => $mc->id_movie, 'id_category' => $mc->id_category])
                    ->toArray();

                // 3. Разбиваем на микро-чанки для отправки на API (по 10 штук)
                $apiChunks = array_chunk($validMoviesData, $apiChunkSize);

                foreach ($apiChunks as $index => $chunk) {
                    $chunkImdbIds = array_column($chunk, 'imdb_id');
                    $chunkLinks = array_values(array_filter($movieCategoriesLinks, fn($link) => in_array($link['imdb_id'], $chunkImdbIds)));

                    $attempt = 0;
                    $success = false;

                    while ($attempt < $maxRetries && !$success) {
                        try {
                            $response = Http::withToken(config('services.kinospectr.api_token'))
                                ->timeout(120)
                                ->retry(2, 1000)
                                ->post(config('services.kinospectr.api_url') . '/api/movies/import', [
                                    'movies' => $chunk,
                                    'movie_categories' => $chunkLinks,
                                    'chunk_index' => $index,
                                ]);

                            if ($response->successful()) {
                                $success = true;
                                $totalProcessed += count($chunk); // Успешно отправлено
                            } else {
                                $attempt++;
                                sleep(pow(2, $attempt)); // Экспоненциальная задержка (1с, 2с, 4с)
                            }
                        } catch (\Exception $e) {
                            $attempt++;
                            sleep(pow(2, $attempt));
                        }
                    }

                    // Если после всех попыток отправка не удалась, считаем эти фильмы пропущенными
                    if (!$success) {
                        $totalSkipped += count($chunk);
                    }
                }

                // 4. Обновляем статус ТОЛЬКО для штатного экспорта
                // Для полной синхронизации статус не меняем (они уже = 2)
                if (!$isFullSync) {
                    $processedImdbIds = array_column($validMoviesData, 'imdb_id');
                    MovieInfo::whereIn('id_movie', $processedImdbIds)->update(['published' => 2]);

                    // Прерываем chunkById после первой итерации для штатного режима (нам нужно только 20 шт.)
                    return false;
                }
            });

        // 5. Формируем итоговый сверенный отчет
        $message = $isFullSync
            ? "Полная синхронизация завершена: успешно отправлено {$totalProcessed} из {$totalPendingCount}. Пропущено (ошибки валидации или сбои API): {$totalSkipped}."
            : sprintf('Успешно экспортировано  %d/%d фильмов. Пропущенно недопустимых: %d.', $totalProcessed, $totalPendingCount, $totalSkipped);

        return [
            'success' => $totalProcessed > 0,
            'sent_count' => $totalProcessed,
            'total_pending' => $totalPendingCount,
            'remaining' => $isFullSync ? 0 : max(0, $totalPendingCount - $totalProcessed),
            'skipped_invalid' => $totalSkipped,
            'message' => $message,
            'type' => ($totalSkipped === 0) ? 'success' : 'partial',
            'status' => 200,
            // Обрезаем лог ошибок до 100 записей, чтобы не "повесить" фронтенд гигантским JSON-ответом
            'skipped_details' => array_slice($validationErrorsLog, 0, 100)
        ];
    }
    public function exportTaxonomies()
    {
        // 1. Получаем пул фильмов
        $statusMovie =  1;

        $movies = MovieInfo::where('published', $statusMovie)
            ->limit(20)
            ->get();

        if ($movies->isEmpty()) {
            return ['success' => false, 'message' => 'No movies to process', 'type' => 'warning', 'status' => 200];
        }

        $movieImdbIds = $movies->pluck('id_movie')->unique()->toArray();

        if (empty($movieImdbIds)) {
            return ['success' => false, 'message' => 'No IMDB IDs found', 'type' => 'warning', 'status' => 200];
        }

        $existingLocalMovies = MovieInfo::whereIn('id_movie', $movieImdbIds)
            ->pluck('id_movie')
            ->toArray();

        $validImdbIds = array_intersect($movieImdbIds, $existingLocalMovies);

        if (empty($validImdbIds)) {
            Log::warning("Taxonomies export skipped: Movies exist in Info but not in Main Movies table yet.", [
                'attempted_ids' => $movieImdbIds
            ]);
            return ['success' => true, 'message' => 'Movies are not fully created in local DB yet. Try again later.', 'type' => 'info', 'status' => 200];
        }

        // Собираем связанные ID таксономий (БЕЗ movie_categories, так как они теперь идут с фильмами)
        $relatedCategoryIds = MovieCategory::whereIn('id_movie', $validImdbIds)
            ->pluck('id_category')
            ->unique()
            ->toArray();

        $pivots = CollectionsCategoriesPivot::whereIn('id_movie', $validImdbIds)->get();

        $relatedCollectionIds = $pivots->pluck('collection_id')->unique()->toArray();
        $relatedFranchiseIds = $pivots->pluck('franchise_id')
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->toArray();

        // Категории
        $categories = [];
        if (!empty($relatedCategoryIds)) {
            $categories = Category::whereIn('id', $relatedCategoryIds)->get()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'index_id' => $category->index_id,
                    'value' => $category->value,
                    'title_en' => $category->title_en,
                    'title_ru' => $category->title_ru,
                ];
            })->toArray();
        }

        // Коллекции
        $collections = [];
        if (!empty($relatedCollectionIds)) {
            $collections = Collection::whereIn('id', $relatedCollectionIds)->get()->map(function ($collection) {
                return [
                    'id' => $collection->id,
                    'category_id' => $collection->category_id,
                    'value' => $collection->value,
                    'label_en' => $collection->label_en,
                    'label_ru' => $collection->label_ru,
                    'created_at' => $collection->created_at,
                    'updated_at' => $collection->updated_at,
                ];
            })->toArray();
        }

        // Франшизы
        $franchises = [];
        if (!empty($relatedFranchiseIds)) {
            $franchises = LocalizingFranchise::whereIn('id', $relatedFranchiseIds)->get()->map(function ($franchise) {
                return [
                    'id' => $franchise->id,
                    'value' => $franchise->value,
                    'label_en' => $franchise->label_en,
                    'label_ru' => $franchise->label_ru,
                    'created_at' => $franchise->created_at,
                    'updated_at' => $franchise->updated_at,
                ];
            })->toArray();
        }

        $collectionsCategoriesPivots = $pivots->map(function ($pivot) {
            return [
                'id_movie'      => $pivot->id_movie,
                'collection_id' => $pivot->collection_id,
                'franchise_id'  => (int) $pivot->franchise_id,
            ];
        })->unique(function ($item) {
            return $item['id_movie'] . '|' . $item['collection_id'] . '|' . $item['franchise_id'];
        })->values()->toArray();
        $taxonomiesData = [
            'categories' => $categories,
            'collections' => $collections,
            'franchises' => $franchises,
            'collections_categories_pivots' => $collectionsCategoriesPivots,
        ];

        $hasData = !empty($categories) || !empty($collections) || !empty($franchises) || !empty($collectionsCategoriesPivots);

        if (!$hasData) {
            return ['success' => true, 'message' => 'No relevant taxonomies for this batch', 'type' => 'info', 'status' => 200];
        }

        try {
            $response = Http::withToken(config('services.kinospectr.api_token'))
                ->timeout(60)
                ->connectTimeout(10)
                ->post(config('services.kinospectr.api_url') . '/api/taxonomies/import', [
                    'taxonomies' => $taxonomiesData,
                ]);

            if ($response->successful()) {
                $totalCount = count($categories) + count($collections) + count($franchises) + count($collectionsCategoriesPivots);
                return [
                    'success' => true,
                    'message' => "Успешно экспортировано таксономий: {$totalCount} записей.",
                    'sent_count' => $totalCount,
                    'type' => 'success',
                    'status' => 200,
                ];
            }

            Log::error('Failed to export taxonomies to Kinospectr', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            throw new \Exception('Failed to export taxonomies: ' . $response->status());

        } catch (\Exception $e) {
            Log::error('Exception during taxonomy export', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage(), 'type' => 'error', 'status' => 500];
        }
    }
    public function exportTags()
    {
        $statusMovie = 1;
        $tags = Tag::all()->map(function ($category) {
            return [
                'id' => $category->id,
                'value' => $category->value,
                'tag_name_en' => $category->tag_name_en,
                'tag_name_ru' => $category->tag_name_ru,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        })->toArray();

        $tagsMoviesPivots = TagsMoviesPivot::whereIn('id_movie', function ($query) use ($statusMovie){
            $query->select('id_movie')
                ->from('movies_info')
                ->where('published', $statusMovie);
        })->get()->map(function ($pivot) {
            return [
                'id_movie' => $pivot->id_movie,
                'id_tag' => $pivot->id_tag,
                'type_film' => $pivot->type_film,
            ];
        })->toArray();

        $tagsData = [
            'tags' => $tags,
            'tags_movies_pivots' => $tagsMoviesPivots,
        ];
        $response = Http::withToken(config('services.kinospectr.api_token'))
            ->post(config('services.kinospectr.api_url') . '/api/tags/import', [
                'tags' => $tagsData,
            ]);

        if ($response->successful()) {
            $totalCount = count($tags) + count($tagsMoviesPivots);
            return [
                'success' => true,
                'message' => "Успешно экспортировано тегов: {$totalCount} записей.",
                'sent_count' => $totalCount,
                'type' => 'success',
                'status' => 200,
            ];
        }

        Log::error('Failed to export tags to Kinospectr', [
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        throw new \Exception('Failed to export tags to Kinospectr');
    }
    protected function validateMovieData(array $data): array
    {
        $errors = [];

        // Список обязательных полей согласно ТЗ
        $requiredFields = [
            'imdb_id',
            'active',
            'type_film',
            'hash_imdb_id',
            'title_en',
            'title_ru',
            'year_release',
            'rating',
            'genres_en',
            'genres_ru',
            'cast_en',
            'cast_ru',
            'countries_en',
            'countries_ru',
            'story_line_en',
            'story_line_ru',
        ];

        foreach ($requiredFields as $field) {
            $value = $data[$field] ?? null;

            // Проверка на пустоту: null, false, empty string, empty array
            if (is_null($value) || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
                $errors[] = "Field '{$field}' is empty or missing.";
            }
        }

        return $errors;
    }
    protected function mapMovieToKinospectrFormat(MovieInfo $movie): ?array
    {
        // Если нет ID фильма, дальше идти бессмысленно
        if (empty($movie->id_movie)) {
            Log::warning("Movie ID is missing for MovieInfo ID: {$movie->id}");
            return null;
        }

        $hasher = new IdHasher($movie->id_movie);
        $hashDecodeId = $hasher->getResult();

        // Если хеш не сгенерировался, тоже проблема
        if (empty($hashDecodeId)) {
            Log::warning("Hash generation failed for Movie ID: {$movie->id_movie}");
            return null;
        }

        $companiesRaw = $movie->companies;
        $companies = [];
        if (!empty($companiesRaw)) {
            $unserialized = unserialize($companiesRaw);
            if (is_array($unserialized) && isset($unserialized['companies'])) {
                $companies = $unserialized['companies'];
            }
        }

        // Helper для безопасного декодирования JSON
        $decodeJson = function ($localization, $field) {
            if (!$localization || empty($localization->$field)) {
                return null;
            }
            $decoded = json_decode($localization->$field, true);
            // json_decode возвращает null при ошибке или если поле было null/пустой строкой
            return $decoded;
        };

        $locales = [
            'En' => $movie->localazingEn,
            'Ru' => $movie->localazingRu,
        ];

        $genresEn = $decodeJson($locales['En'], 'genres');
        $genresRu = $decodeJson($locales['Ru'], 'genres');

        $castEn = $decodeJson($locales['En'], 'cast');
        $castRu = $decodeJson($locales['Ru'], 'cast');

        $directorsEn = $decodeJson($locales['En'], 'directors');
        $directorsRu = $decodeJson($locales['Ru'], 'directors');

        $writersEn = $decodeJson($locales['En'], 'writers');
        $writersRu = $decodeJson($locales['Ru'], 'writers');

        $countriesEn = $decodeJson($locales['En'], 'countries');
        $countriesRu = $decodeJson($locales['Ru'], 'countries');

        $storyLineEn = $locales['En'] ? $locales['En']->story_line : null;
        $storyLineRu = $locales['Ru'] ? $locales['Ru']->story_line : null;

        $releaseDateEn = $locales['En'] ? $locales['En']->release_date : null;
        $releaseDateRu = $locales['Ru'] ? $locales['Ru']->release_date : null;

        return [
            'imdb_id' => $movie->id_movie,
            'active' => $movie->published,
            'type_film' => $movie->type_film,
            'hash_imdb_id' => $hashDecodeId,
            'title_en' => $movie->original_title ?? $movie->title,
            'title_ru' => $movie->title,
            'year_release' => $movie->year_release,
            'restrictions' => $movie->restrictions,
            'runtime' => $movie->runtime,
            'rating' => $movie->rating,
            'budget' => $movie->budget,
            'companies' => $companies,
            'genres_en' => $genresEn,
            'genres_ru' => $genresRu,
            'cast_en' => $castEn,
            'cast_ru' => $castRu,
            'directors_en' => $directorsEn,
            'directors_ru' => $directorsRu,
            'writers_en' => $writersEn,
            'writers_ru' => $writersRu,
            'countries_en' => $countriesEn,
            'countries_ru' => $countriesRu,
            'story_line_en' => $storyLineEn,
            'story_line_ru' => $storyLineRu,
            'release_date_en' => $releaseDateEn,
            'release_date_ru' => $releaseDateRu,
            'poster' => $movie->poster ?? null,
            'poster_ru' => $movie->poster_ru ?? null,
        ];
    }

    public function exportFullTaxonomies(): array
    {
        try {
            // Собираем ВСЕ данные, без ограничений по фильмам
            $categories = Category::all(['id', 'index_id', 'value', 'title_en', 'title_ru'])->toArray();
            $collections = Collection::all(['id', 'category_id', 'value', 'label_en', 'label_ru', 'created_at', 'updated_at'])->toArray();

            // Обратите внимание: в отправляющем сервисе модель может называться LocalizingFranchise
            $franchises = \App\Models\LocalizingFranchise::all(['id', 'value', 'label_en', 'label_ru', 'created_at', 'updated_at'])->toArray();

            $pivots = CollectionsCategoriesPivot::all(['id_movie', 'collection_id', 'franchise_id'])->toArray();

            $taxonomiesData = [
                'categories' => $categories,
                'collections' => $collections,
                'franchises' => $franchises,
                'collections_categories_pivots' => $pivots,
            ];

            $response = Http::withToken(config('services.kinospectr.api_token'))
                ->timeout(120)
                ->post(config('services.kinospectr.api_url') . '/api/taxonomies/import-full', [
                    'taxonomies' => $taxonomiesData,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 200,
                    'data' => $response->json(),
                ];
            }

            throw new \Exception('Failed to export full taxonomies: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('Exception during full taxonomy export', ['error' => $e->getMessage()]);
            return ['success' => false, 'status' => 500, 'message' => $e->getMessage()];
        }
    }

    public function exportFullTags(): array
    {
        try {
            $tags = Tag::all(['id', 'value', 'tag_name_en', 'tag_name_ru', 'created_at', 'updated_at'])->toArray();
            $pivots = TagsMoviesPivot::all(['id_movie', 'id_tag', 'type_film'])->toArray();

            $tagsData = [
                'tags' => $tags,
                'tags_movies_pivots' => $pivots,
            ];

            $response = Http::withToken(config('services.kinospectr.api_token'))
                ->timeout(120)
                ->post(config('services.kinospectr.api_url') . '/api/tags/import-full', [
                    'tags' => $tagsData,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 200,
                    'data' => $response->json(),
                ];
            }

            throw new \Exception('Failed to export full tags: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('Exception during full tags export', ['error' => $e->getMessage()]);
            return ['success' => false, 'status' => 500, 'message' => $e->getMessage()];
        }
    }
}
