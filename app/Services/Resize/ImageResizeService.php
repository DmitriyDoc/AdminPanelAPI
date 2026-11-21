<?php

namespace App\Services\Resize;

use App\Events\ResizeReportEvent;
use App\Models\AssignPoster;
use App\Models\TMDb\MoviePoster;
use App\Models\TMDb\MovieBackdrop;
use App\Services\IdHasher;
use App\Services\ApiRequestImages;
use Illuminate\Support\Facades\Log;

class ImageResizeService
{
    private const BACKDROP_FETCH_LIMIT = 10;

    public function __construct(
        private ImageProcessor $processor,
        private MediaApiUploader $uploader,
        private ApiRequestImages $apiRequest
    ) {}

    public function process(string $originalImdbId, string $slug): void
    {
        $hasher = new IdHasher($originalImdbId);
        $hashedId = $hasher->getResult();

        $assignData = AssignPoster::where('id_movie', $originalImdbId)->first();

        if (!$assignData) {
            event(new ResizeReportEvent([
                'status' => 'warning',
                'title' => 'Пропущено: нет назначенных постеров для обработки (assign_posters).',
            ]));
            return;
        }

        $typeFilmId = $assignData->type_film;
        $typeSlug = is_numeric($slug) ? getTableSegmentOrTypeId($slug) : $slug;

        $legacyPosterClass = 'App\\Models\\Posters' . $typeSlug;
        $legacyImageClass  = 'App\\Models\\Images' . $typeSlug;

        $this->apiRequest->clearImages((string)$typeFilmId, $hashedId);

        $posterSource = 'tmdb';
        $originalPosterId = $assignData->id_poster_original;

        if ($originalPosterId) {
            $isTmdb = MoviePoster::where('id', $originalPosterId)->where('id_movie', $originalImdbId)->exists();
            if ($isTmdb) {
                $posterSource = 'tmdb';
            } elseif (class_exists($legacyPosterClass)) {
                $isImdb = $legacyPosterClass::where('id', $originalPosterId)->where('id_movie', $originalImdbId)->exists();
                if ($isImdb) $posterSource = 'imdb';
            }
        }

        // 1. Обработка постеров
        event(new ResizeReportEvent([
            'status' => 'info',
            'title' => 'Начало обработки и сжатия постеров...',
        ]));
        $this->processAssignedPosters($originalImdbId, $hashedId, $typeFilmId, $assignData, $legacyPosterClass);

        // 2. Обработка изображений (backdrops)
        event(new ResizeReportEvent([
            'status' => 'info',
            'title' => 'Начало обработки и сжатия изображений...',
        ]));
        $this->processImages($originalImdbId, $hashedId, $typeFilmId, $legacyImageClass, $posterSource);
        $this->updateMoviePosterUrl($originalImdbId, $hashedId, $typeFilmId,$assignData);
        // 3. Финал
        event(new ResizeReportEvent([
            'status' => 'success',
            'title' => 'Компрессия и загрузка всех изображений завершены!',
        ]));
    }

    private function processAssignedPosters(string $originalImdbId, string $hashedId, int $typeFilmId, AssignPoster $assign, string $legacyPosterClass): void
    {
        $categories = [
            'original_poster' => $assign->id_poster_original ? [$assign->id_poster_original] : [],
            'russian_poster' => $assign->id_poster_ru ? [$assign->id_poster_ru] : [],
            'characters_posters' => json_decode($assign->id_posters_characters ?? '[]', true) ?: [],
            'alt_posters' => json_decode($assign->id_posters_alternative ?? '[]', true) ?: [],
            'wallpaper' => $assign->id_wallpaper ? [$assign->id_wallpaper] : [],
        ];

        foreach ($categories as $dirName => $posterIds) {
            if (empty($posterIds)) continue;

            $report = ['files' => [], 'issues' => []];
            $batchFiles = [];

            // 1. Сначала собираем файлы для загрузки
            foreach ($posterIds as $posterId) {
                $url = $this->resolveImageUrl($originalImdbId, $posterId, $legacyPosterClass, true);

                if (!$url) {
                    $report['issues'][] = "ID {$posterId}: URL изображения не найден";
                    continue;
                }

                $result = $this->processor->processPoster($posterId, $url, $hashedId, $typeFilmId, $dirName);

                if (isset($result['success']) && $result['success']) {
                    $batchFiles[] = [
                        'fileName' => $posterId,
                        'paths' => $result['paths'],
                        'dirPosterName' => $dirName,
                    ];
                } else {
                    $reason = $result['reason'] ?? 'unknown';
                    $report['issues'][] = $reason === 'download' ? "ID {$posterId}: Ошибка скачивания" : "ID {$posterId}: Ошибка обработки";
                }
            }

            // 2. 🔥 ЧЕСТНАЯ ЗАГРУЗКА: Проверяем, действительно ли файлы ушли на API
            $uploadSuccess = false;
            if (!empty($batchFiles)) {
                $uploadSuccess = $this->uploader->uploadBatch($batchFiles, $typeFilmId, $hashedId);
            } else {
                // Если файлов не было, считаем это успехом (пустой список)
                $uploadSuccess = true;
            }

            // 3. Формируем отчет только на основе РЕАЛЬНОГО результата загрузки
            $total = count($posterIds);
            if ($uploadSuccess && !empty($batchFiles)) {
                foreach ($batchFiles as $batch) {
                    $filePath = $batch['paths']['full_size'] ?? reset($batch['paths']);
                    $report['files'][] = basename($filePath);
                }
            } elseif (!$uploadSuccess && !empty($batchFiles)) {
                $report['issues'][] = "Критическая ошибка: не удалось загрузить файлы на media-api";
            }

            $successCount = count($report['files']);
            $status = 'error';
            if ($successCount === $total && $total > 0) {
                $status = 'success';
            } elseif ($successCount > 0) {
                $status = 'warning';
            }

            event(new ResizeReportEvent([
                'status' => $status,
                'category' => $dirName,
                'total' => $total,
                'success_count' => $successCount,
                'files' => $report['files'],
                'issues' => $report['issues'],
            ]));
        }
    }
    private function processImages(string $originalImdbId, string $hashedId, int $typeFilmId, string $legacyImageClass, string $posterSource): void
    {
        $report = ['files' => [], 'issues' => []];
        $imagesToProcess = [];
        $batchFiles = [];

        // 1. Собираем список изображений для обработки
        if ($posterSource === 'imdb' && class_exists($legacyImageClass)) {
            $imagesToProcess = $legacyImageClass::where('id_movie', $originalImdbId)
                ->orderBy('id', 'asc')
                ->limit(self::BACKDROP_FETCH_LIMIT)
                ->get(['id', 'src']);
        } else {
            $tmdbImages = MovieBackdrop::where('id_movie', $originalImdbId)
                ->orderBy('id', 'asc')
                ->limit(self::BACKDROP_FETCH_LIMIT)
                ->get(['id', 'file_path']);

            foreach ($tmdbImages as $img) {
                $imagesToProcess[] = (object)[
                    'id' => $img->id,
                    'src' => env('TMDB_IMAGE_BASE_URL').'/t/p/original' . $img->file_path
                ];
            }
        }

        // 2. Обрабатываем (сжимаем) каждое изображение
        foreach ($imagesToProcess as $image) {
            if (empty($image->src)) {
                $report['issues'][] = "ID {$image->id}: Пустой URL";
                continue;
            }

            $result = $this->processor->processBackdrop($image->id, $image->src, $hashedId, $typeFilmId);

            if (isset($result['success']) && $result['success']) {
                // 🔥 NEW: Не добавляем в отчет сразу! Добавляем в батч для реальной загрузки
                $batchFiles[] = [
                    'fileName' => $image->id,
                    'paths' => $result['paths'],
                    'dirPosterName' => 'images', // Важно для MediaApiUploader
                ];
            } else {
                $reason = $result['reason'] ?? 'unknown';
                if ($reason === 'download') {
                    $report['issues'][] = "ID {$image->id}: Ошибка скачивания задника";
                } else {
                    $report['issues'][] = "ID {$image->id}: Ошибка обработки задника";
                }
            }
        }

        // 3. 🔥 NEW: ЧЕСТНАЯ ЗАГРУЗКА на media-api
        $uploadSuccess = true;
        if (!empty($batchFiles)) {
            // Разбиваем на чанки по 10 файлов, как было в оригинале
            $chunks = array_chunk($batchFiles, 10);
            foreach ($chunks as $chunk) {
                $chunkSuccess = $this->uploader->uploadBatch($chunk, $typeFilmId, $hashedId);
                if (!$chunkSuccess) {
                    $uploadSuccess = false; // Если хотя бы один чанк упал, помечаем общий статус
                }
            }
        }

        // 4. Формируем отчет ТОЛЬКО на основе реального результата загрузки
        if ($uploadSuccess && !empty($batchFiles)) {
            foreach ($batchFiles as $batch) {
                $filePath = $batch['paths']['full_size'] ?? reset($batch['paths']);
                $report['files'][] = basename($filePath);
            }
        } elseif (!$uploadSuccess && !empty($batchFiles)) {
            $report['issues'][] = "Критическая ошибка: не удалось загрузить задники на media-api";
        }

        // 5. Определяем статус и отправляем событие
        $total = count($imagesToProcess);
        $successCount = count($report['files']);

        $status = $total === 0 ? 'info' : ($successCount === $total ? 'success' : ($successCount > 0 ? 'warning' : 'error'));

        if ($total > 0) {
            event(new ResizeReportEvent([
                'status' => $status,
                'category' => 'backdrops',
                'total' => $total,
                'success_count' => $successCount,
                'files' => $report['files'],
                'issues' => $report['issues'],
            ]));
        }
    }
    private function resolveImageUrl(string $originalImdbId, int $mediaId, ?string $legacyModelClass, bool $isPoster): ?string
    {
        $tmdbModelClass = $isPoster ? MoviePoster::class : MovieBackdrop::class;

        $tmdbRecord = $tmdbModelClass::where('id', $mediaId)->where('id_movie', $originalImdbId)->first();
        if ($tmdbRecord) {
            return env('TMDB_IMAGE_BASE_URL').'/t/p/original' . $tmdbRecord->file_path;
        }

        if ($legacyModelClass && class_exists($legacyModelClass)) {
            $legacyRecord = $legacyModelClass::where('id', $mediaId)->where('id_movie', $originalImdbId)->first();
            if ($legacyRecord && !empty($legacyRecord->src)) {
                return trim($legacyRecord->src);
            }
        }

        return null;
    }
    // Добавьте этот метод в класс ImageResizeService
    // 🔥 ДОБАВЛЯЕМ $assignData в аргументы
    private function updateMoviePosterUrl(string $originalImdbId, string $hashedId, int $typeFilmId, \App\Models\AssignPoster $assignData): void
    {
        try {
            $movie = \App\Models\MovieInfo::where('id_movie', $originalImdbId)->first();

            if ($movie && $assignData->id_poster_original) {
                // Формируем URL. ВНИМАНИЕ: сверьте этот путь с тем, как его ожидает ваш media-api!
                // В логах было: /images/1/xr7laqgxzxw9aoe/posters/original_poster
                // Убедитесь, что здесь структура совпадает с тем, что сохраняет MediaApiUploader
                $newPosterUrl = env('API_HOST_URL') . "/images/{$typeFilmId}/{$hashedId}/posters/original_poster/full_size/{$assignData->id_poster_original}.jpg";

                $movie->update(['poster' => $newPosterUrl]);

                //Log::info("DB-UPDATED-SUCCESS ---> Movie {$originalImdbId} poster updated to: {$newPosterUrl}");
            } else {
                Log::warning("DB-UPDATE-SKIPPED ---> No original poster ID found for movie {$originalImdbId}");
            }
        } catch (\Exception $e) {
            Log::error("DB-UPDATE-FAILED ---> ERROR: {$e->getMessage()}");
        }
    }
}
