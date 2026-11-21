<?php

namespace App\Services\TMDb;

use App\Models\TMDb\MovieLogo;
use App\Models\TMDb\MovieBackdrop;
use Illuminate\Support\Facades\Log;

class TmdbImagesService extends BaseTmdbService
{
    public function getImagesByImdb(string $imdbId, int $offset = 0, int $limit = 10): array
    {
        // ==========================================
        // ШАГ 1: СБОР ДАННЫХ ТОЛЬКО ИЗ ЛОКАЛЬНОЙ БД (ЛОГО И ЗАДНИКИ)
        // ==========================================
        $localLogos = MovieLogo::where('id_movie', $imdbId)->get();
        $localBackdrops = MovieBackdrop::where('id_movie', $imdbId)->get();

        // Форматируем локальные логотипы
        $dbLogos = ['ru' => [], 'en' => []];
        foreach ($localLogos as $logo) {
            if (in_array($logo->locale, ['ru', 'en'])) {
                $dbLogos[$logo->locale][] = $this->formatImageFromDb($logo);
            }
        }

        // Форматируем локальные задники (vote_average по умолчанию 0, так как в БД его нет)
        $dbBackdrops = [];
        foreach ($localBackdrops as $backdrop) {
            $dbBackdrops[] = array_merge($this->formatImageFromDb($backdrop), [
                'vote_average' => 0
            ]);
        }

        // ==========================================
        // ШАГ 2: ПРОВЕРКА УСЛОВИЙ ДЛЯ ЗАПРОСА К API
        // ==========================================
        $logosAreEmpty = (count($dbLogos['ru']) + count($dbLogos['en'])) === 0;
        $backdropsAreInsufficient = count($dbBackdrops) < $limit;

        $needApiFallback = $logosAreEmpty || $backdropsAreInsufficient;

        $tmdbId = null;
        $mediaType = 'movie';
        $title = 'Unknown';

        if ($needApiFallback) {
            try {
                // 1. Находим TMDB ID
                $findResponse = $this->makeRequest("/find/{$imdbId}", [
                    'external_source' => 'imdb_id',
                    'language' => 'ru-RU'
                ]);

                if (!empty($findResponse['movie_results'])) {
                    $tmdbId = $findResponse['movie_results'][0]['id'];
                    $mediaType = 'movie';
                    $title = $findResponse['movie_results'][0]['title'];
                } elseif (!empty($findResponse['tv_results'])) {
                    $tmdbId = $findResponse['tv_results'][0]['id'];
                    $mediaType = 'tv';
                    $title = $findResponse['tv_results'][0]['name'];
                }

                if ($tmdbId) {
                    // 2. Получаем изображения из API
                    $apiImagesResponse = $this->makeRequest("/{$mediaType}/{$tmdbId}/images", [
                        'include_image_language' => 'ru,en,null'
                    ]);

                    // 3. МЕРДЖИМ (ОБЪЕДИНЯЕМ) ДАННЫЕ API С БД, избегая дубликатов по file_path

                    // Мердж Логотипов
                    $apiLogos = $apiImagesResponse['logos'] ?? [];
                    foreach ($apiLogos as $logo) {
                        $lang = $logo['iso_639_1'] ?? 'null';
                        if (in_array($lang, ['ru', 'en'])) {
                            // Проверка на дубликат по file_path
                            $isDuplicate = false;
                            foreach ($dbLogos[$lang] as $existing) {
                                if ($existing['path'] === $logo['file_path']) {
                                    $isDuplicate = true;
                                    break;
                                }
                            }
                            if (!$isDuplicate) {
                                $dbLogos[$lang][] = [
                                    'path' => $logo['file_path'],
                                    'url_original' => self::IMAGE_ORIGINAL_PREFIX . $logo['file_path'],
                                    'url_w500' => "{$this->imageUrl}/w500{$logo['file_path']}",
                                    'url_w300' => "{$this->imageUrl}/w300{$logo['file_path']}",
                                    'url_w200' => "{$this->imageUrl}/w200{$logo['file_path']}",
                                    'width' => $logo['width'],
                                    'height' => $logo['height'],
                                    'aspect_ratio' => $logo['aspect_ratio']
                                ];
                            }
                        }
                    }

                    // Мердж Задников
                    $apiBackdrops = $apiImagesResponse['backdrops'] ?? [];
                    $mergedBackdropsMap = [];

                    // Сначала добавляем все локальные (они уже в $dbBackdrops)
                    foreach ($dbBackdrops as $b) {
                        $mergedBackdropsMap[$b['path']] = $b;
                    }

                    // Затем добавляем API, если такого file_path еще нет
                    foreach ($apiBackdrops as $backdrop) {
                        if (is_null($backdrop['iso_639_1'])) {
                            $path = $backdrop['file_path'];
                            if (!isset($mergedBackdropsMap[$path])) {
                                $mergedBackdropsMap[$path] = [
                                    'path' => $path,
                                    'url_original' => self::IMAGE_ORIGINAL_PREFIX . $path,
                                    'url_w1280' => "{$this->imageUrl}/w1280{$path}",
                                    'url_w780' => "{$this->imageUrl}/w780{$path}",
                                    'url_w300' => "{$this->imageUrl}/w300{$path}",
                                    'width' => $backdrop['width'],
                                    'height' => $backdrop['height'],
                                    'aspect_ratio' => $backdrop['aspect_ratio'],
                                    'vote_average' => $backdrop['vote_average'] ?? 0
                                ];
                            }
                        }
                    }

                    // Преобразуем карту обратно в массив и сортируем по vote_average (от высшего к низшему)
                    $dbBackdrops = array_values($mergedBackdropsMap);
                    usort($dbBackdrops, fn($a, $b) => $b['vote_average'] <=> $a['vote_average']);
                }
            } catch (\Exception $e) {
                // Если API упал, мы просто продолжаем с теми данными, что есть в БД.
                Log::warning("TMDB API fallback failed for {$imdbId}, using local DB data only: " . $e->getMessage());
            }
        }

        // ==========================================
        // ШАГ 3: ФИНАЛЬНЫЙ ФОЛБЭК (ЗАГЛУШКА)
        // ==========================================
        if (empty($dbBackdrops)) {
            $dbBackdrops[] = [
                'path' => '/placeholder_backdrop.svg',
                'url_original' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080" viewBox="0 0 1920 1080"%3E%3Crect fill="%23222" width="1920" height="1080"/%3E%3Ctext fill="%23666" font-family="sans-serif" font-size="40" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Backdrop%3C/text%3E%3C/svg%3E',
                'url_w1280' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720"%3E%3Crect fill="%23222" width="1280" height="720"/%3E%3Ctext fill="%23666" font-family="sans-serif" font-size="30" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Backdrop%3C/text%3E%3C/svg%3E',
                'url_w780' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="780" height="438" viewBox="0 0 780 438"%3E%3Crect fill="%23222" width="780" height="438"/%3E%3Ctext fill="%23666" font-family="sans-serif" font-size="24" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Backdrop%3C/text%3E%3C/svg%3E',
                'url_w300' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="169" viewBox="0 0 300 169"%3E%3Crect fill="%23222" width="300" height="169"/%3E%3Ctext fill="%23666" font-family="sans-serif" font-size="16" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Backdrop%3C/text%3E%3C/svg%3E',
                'width' => 1920,
                'height' => 1080,
                'aspect_ratio' => 1.778,
                'vote_average' => 0,
                'is_placeholder' => true
            ];
        }

        if (count($dbLogos['ru']) === 0 && count($dbLogos['en']) === 0) {
            $dbLogos['en'][] = [
                'path' => '/placeholder_logo.svg',
                'url_original' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="500" height="200" viewBox="0 0 500 200"%3E%3Crect fill="%23333" width="500" height="200"/%3E%3Ctext fill="%23777" font-family="sans-serif" font-size="24" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Logo%3C/text%3E%3C/svg%3E',
                'url_w500' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="500" height="200" viewBox="0 0 500 200"%3E%3Crect fill="%23333" width="500" height="200"/%3E%3Ctext fill="%23777" font-family="sans-serif" font-size="24" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Logo%3C/text%3E%3C/svg%3E',
                'url_w300' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="300" height="120" viewBox="0 0 300 120"%3E%3Crect fill="%23333" width="300" height="120"/%3E%3Ctext fill="%23777" font-family="sans-serif" font-size="18" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Logo%3C/text%3E%3C/svg%3E',
                'url_w200' => 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="80" viewBox="0 0 200 80"%3E%3Crect fill="%23333" width="200" height="80"/%3E%3Ctext fill="%23777" font-family="sans-serif" font-size="14" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo Logo%3C/text%3E%3C/svg%3E',
                'width' => 500,
                'height' => 200,
                'aspect_ratio' => 2.5,
                'is_placeholder' => true
            ];
        }

        // ==========================================
        // ШАГ 4: ПАГИНАЦИЯ И ФОРМИРОВАНИЕ ОТВЕТА
        // ==========================================
        $totalBackdrops = count($dbBackdrops);
        $paginatedBackdrops = array_slice($dbBackdrops, $offset, $limit);

        return [
            'tmdb_id' => $tmdbId,
            'type' => $mediaType,
            'title' => $title,
            'logos' => $dbLogos,
            'backdrops' => $paginatedBackdrops,
            'total_backdrops' => $totalBackdrops
        ];
    }

    /**
     * Унифицированный метод форматирования записи из БД в формат ответа
     */
    private function formatImageFromDb($model): array
    {
        return [
            'path' => $model->file_path,
            'url_original' => self::IMAGE_ORIGINAL_PREFIX . $model->file_path,
            'url_w1280' => "{$this->imageUrl}/w1280{$model->file_path}",
            'url_w780' => "{$this->imageUrl}/w780{$model->file_path}",
            'url_w500' => "{$this->imageUrl}/w500{$model->file_path}",
            'url_w300' => "{$this->imageUrl}/w300{$model->file_path}",
            'url_w200' => "{$this->imageUrl}/w200{$model->file_path}",
            'width' => $model->width,
            'height' => $model->height,
            'aspect_ratio' => ($model->width && $model->height) ? round($model->width / $model->height, 3) : 0
        ];
    }
}
