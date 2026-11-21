<?php

namespace App\Http\Controllers;

use App\Models\ImdbChart;
use App\Models\ImdbChartMovie;
use App\Models\AssignPoster;
use App\Models\TMDb\MoviePoster;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Collection;

class ChartsController extends Controller
{
    public function index(Request $request): array
    {
        $locale = app()->getLocale();
        $titleField = $locale === 'ru' ? 'title_ru' : 'title_en';

        $allowedSortFields = ['chart_id', 'title_en', 'title_ru', 'movies_count', 'last_parsed_at', 'created_at', 'updated_at'];
        $allowedSortDirections = ['desc', 'asc'];

        $limit = $request->query('limit', 50);
        $sortDir = strtolower($request->query('spin', 'desc'));
        $sortBy = $request->query('orderBy', 'updated_at');

        if (!in_array($sortBy, $allowedSortFields)) $sortBy = 'updated_at';
        if (!in_array($sortDir, $allowedSortDirections)) $sortDir = 'desc';

        $query = ImdbChart::query();

        if ($searchQuery = $request->query('search')) {
            $safeQuery = trim(strtolower(strip_tags($searchQuery)));
            $query->where(function($q) use ($safeQuery, $titleField) {
                $q->where('title_en', 'like', '%' . $safeQuery . '%')
                    ->orWhere('title_ru', 'like', '%' . $safeQuery . '%')
                    ->orWhere('chart_id', 'like', '%' . $safeQuery . '%');
            });
        }

        $charts = $query->orderBy($sortBy, $sortDir)->paginate($limit)->toArray();

        if (!empty($charts['data'])) {
            foreach ($charts['data'] as $key => $item) {
                $charts['data'][$key]['title'] = $item[$titleField] ?? $item['title_en'];
                $charts['data'][$key]['last_parsed_at'] = !empty($item['last_parsed_at']) ? date('Y-m-d', strtotime($item['last_parsed_at'])) : '';
                $charts['data'][$key]['updated_at'] = !empty($item['updated_at']) ? date('Y-m-d', strtotime($item['updated_at'])) : '';
                $charts['data'][$key]['created_at'] = !empty($item['created_at']) ? date('Y-m-d', strtotime($item['created_at'])) : '';
            }
        }

        $charts['locale'] = LanguageController::localizingChartsList();
        return $charts;
    }

    public function showMovies(Request $request, string $chartId): array
    {
        $locale = app()->getLocale();
        $titleField = transformTitleByLocale();
        $currentLocaleCode = $locale === 'ru' ? 'ru' : 'en';

        $allowedSortFields = ['position', 'published', 'year_release', 'created_at', 'updated_at'];
        $allowedSortDirections = ['desc', 'asc'];

        $limit = (int) $request->query('limit', 50);
        $sortDir = strtolower($request->query('spin', 'asc'));
        $sortBy = $request->query('orderBy', 'position');

        if (!in_array($sortBy, $allowedSortFields)) $sortBy = 'position';
        if (!in_array($sortDir, $allowedSortDirections)) $sortDir = 'asc';

        $chart = ImdbChart::where('chart_id', $chartId)->first();

        if (!$chart) {
            return [
                'data' => [], 'total' => 0,
                'locale' => LanguageController::localizingChartMoviesList(),
                'chart_info' => null, 'error' => 'Chart not found'
            ];
        }

        $chartMovies = ImdbChartMovie::where('chart_id', $chartId)
            ->orderBy('position', 'asc')
            ->get()
            ->keyBy('movie_id');

        $movieIds = $chartMovies->keys()->toArray();

        if (empty($movieIds)) {
            return [
                'data' => [], 'total' => 0,
                'locale' => LanguageController::localizingChartMoviesList(),
                'chart_info' => [
                    'chart_id' => $chart->chart_id,
                    'title' => $locale === 'ru' ? $chart->title_ru : $chart->title_en,
                    'movies_count' => $chart->movies_count,
                ],
            ];
        }

        $model = modelByName('MovieInfo');
        $query = $model->select('id_movie', $titleField, 'published', 'year_release', 'type_film', 'created_at', 'updated_at')
            ->whereIn('id_movie', $movieIds);

        if ($searchQuery = $request->query('search')) {
            $safeQuery = trim(strtolower(strip_tags($searchQuery)));
            $allowedFilterFields = $model->getFillable();
            $query->where(function($q) use ($safeQuery, $allowedFilterFields) {
                $q->where($allowedFilterFields[4] ?? 'title', 'like', '%' . $safeQuery . '%')
                    ->orWhere($allowedFilterFields[5] ?? 'original_title', 'like', '%' . $safeQuery . '%')
                    ->orWhere($allowedFilterFields[1] ?? 'id_movie', 'like', '%' . $safeQuery . '%');
            });
        }

        $allMovies = $query->get();

        foreach ($allMovies as $movie) {
            $movie->position = $chartMovies[$movie->id_movie]->position ?? 999999;
        }

        if ($sortBy === 'position') {
            $allMovies = $allMovies->sortBy('position', SORT_REGULAR, $sortDir === 'desc');
        } else {
            $allMovies = $allMovies->sortBy($sortBy, SORT_REGULAR, $sortDir === 'desc');
        }

        $total = $allMovies->count();
        $offset = ((int) $request->query('page', 1) - 1) * $limit;
        $paginatedMovies = $allMovies->slice($offset, $limit)->values();
        $paginatedMovieIds = $paginatedMovies->pluck('id_movie')->toArray();
        $moviesByType = $paginatedMovies->groupBy('type_film');

        // 👇 ИЗМЕНЕНО: сохраняем и 'id', и 'srcset' для строгой проверки источника
        $legacyPostersMap = [];
        foreach ($moviesByType as $typeFilm => $movies) {
            $typeMovieSlug = getTableSegmentOrTypeId($typeFilm);
            $posterModelName = 'App\\Models\\Posters' . $typeMovieSlug;

            if (class_exists($posterModelName)) {
                $currentMovieIds = $movies->pluck('id_movie')->toArray();
                $posters = $posterModelName::whereIn('id_movie', $currentMovieIds)
                    ->get(['id', 'id_movie', 'srcset'])
                    ->groupBy('id_movie');

                foreach ($posters as $mid => $posterCollection) {
                    $firstPoster = $posterCollection->first();
                    $legacyPostersMap[$mid] = [
                        'id' => $firstPoster->id ?? null,
                        'srcset' => $firstPoster->srcset ?? ''
                    ];
                }
            }
        }

        $assignedPostersMap = AssignPoster::whereIn('id_movie', $paginatedMovieIds)
            ->get()
            ->keyBy('id_movie');

        $moviePostersMap = MoviePoster::whereIn('id_movie', $paginatedMovieIds)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('id_movie');

        $moviesData = [];
        foreach ($paginatedMovies as $item) {
            $itemArr = $item->toArray();
            $typeMovieSlug = getTableSegmentOrTypeId($itemArr['type_film']);

            $legacyData = $legacyPostersMap[$item->id_movie] ?? ['id' => null, 'srcset' => ''];
            $assignedPoster = $assignedPostersMap[$item->id_movie] ?? null;
            $tmdbPosters = $moviePostersMap[$item->id_movie] ?? collect();

            // ==========================================
            // 👇 СТРОГАЯ ПРОВЕРКА ИСТОЧНИКА (Без N+1)
            // ==========================================
            $posterSource = '-';
            if ($assignedPoster && !empty($assignedPoster->id_poster_original)) {
                $assignedId = $assignedPoster->id_poster_original;

                // 1. Проверяем TMDB: есть ли этот ID среди постеров этого фильма
                $isTmdb = false;
                foreach ($tmdbPosters as $p) {
                    if ($p->id == $assignedId) {
                        $isTmdb = true;
                        break;
                    }
                }

                if ($isTmdb) {
                    $posterSource = 'tmdb';
                }
                // 2. Проверяем IMDb: совпадает ли назначенный ID с ID в legacy таблице этого фильма
                elseif ($legacyData['id'] && (string)$legacyData['id'] === (string)$assignedId) {
                    $posterSource = 'imdb';
                }
            }
            // ==========================================

            $posterUrl = $this->getPosterUrlForChart(
                $item->id_movie,
                $typeMovieSlug,
                $legacyData['srcset'],
                $assignedPoster,
                $tmdbPosters,
                $currentLocaleCode
            );

            $moviesData[] = [
                'id_movie' => $item->id_movie,
                'title' => $itemArr[$titleField] ?? $itemArr['original_title'] ?? '',
                'published' => statusSelection($itemArr['published']) ?? [],
                'year_release' => $itemArr['year_release'],
                'type_film' => $itemArr['type_film'],
                'type_film_string' => $typeMovieSlug,
                'created_at' => !empty($itemArr['created_at']) ? date('Y-m-d', strtotime($itemArr['created_at'])) : '',
                'updated_at' => !empty($itemArr['updated_at']) ? date('Y-m-d', strtotime($itemArr['updated_at'])) : '',
                'position' => $item->position,
                'poster' => $posterUrl,
                'poster_source' => $posterSource, // 👇 ДОБАВЛЕНО
            ];
        }

        return [
            'data' => $moviesData,
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $request->query('page', 1),
            'last_page' => ceil($total / $limit),
            'locale' => LanguageController::localizingChartMoviesList(),
            'chart_info' => [
                'chart_id' => $chart->chart_id,
                'title' => $locale === 'ru' ? $chart->title_ru : $chart->title_en,
                'movies_count' => $chart->movies_count,
            ],
        ];
    }

    private function getPosterUrlForChart(
        string $idMovie,
        string $typeMovieSlug,
        ?string $legacySrcset,
        ?object $assignedPoster,
        ?Collection $moviePosters,
        string $currentLocale
    ): string {

        // ПРИОРИТЕТ 1: Назначенный постер для текущей локали
        if ($assignedPoster) {
            $assignField = ($currentLocale === 'ru') ? 'id_poster_ru' : 'id_poster_original';
            $assignedId = $assignedPoster->$assignField ?? null;

            if ($assignedId && $moviePosters) {
                $targetPoster = $moviePosters->firstWhere('id', $assignedId);
                if ($targetPoster) {
                    return $this->getPosterDisplayUrl($targetPoster);
                }
            }
        }

        // ПРИОРИТЕТ 2: Любой постер из MoviePoster с совпадающей locale
        if ($moviePosters) {
            $localePoster = $moviePosters->firstWhere('locale', $currentLocale);
            if ($localePoster) {
                return $this->getPosterDisplayUrl($localePoster);
            }
        }

        // ПРИОРИТЕТ 3: Фоллбэк на оригинальный постер
        if ($assignedPoster) {
            $originalAssignedId = $assignedPoster->id_poster_original ?? null;
            if ($originalAssignedId && $moviePosters) {
                $targetPoster = $moviePosters->firstWhere('id', $originalAssignedId);
                if ($targetPoster) {
                    return $this->getPosterDisplayUrl($targetPoster);
                }
            }
        }

        // ПРИОРИТЕТ 4: Любой оригинальный постер (locale = null, 'en' или '')
        if ($moviePosters) {
            $originalPoster = $moviePosters->first(function($p) {
                return in_array($p->locale, [null, 'en', ''], true);
            });
            if ($originalPoster) {
                return $this->getPosterDisplayUrl($originalPoster);
            }
        }

        // ПРИОРИТЕТ 5: Legacy srcset (IMDb)
        if ($legacySrcset) {
            $img = explode(',', $legacySrcset);
            if (!empty($img[0])) {
                return trimAfterImageExtension($img[0]) ?? '';
            }
        }

        return '';
    }

    /**
     * Получает URL постера с акцессором или fallback
     */
    private function getPosterDisplayUrl(object $poster): string
    {
        // Пробуем акцессор display_url
        if (isset($poster->display_url) && !empty($poster->display_url)) {
            return $poster->display_url;
        }

        // Fallback: строим URL из file_path
        if (!empty($poster->file_path)) {
            return env('TMDB_IMAGE_BASE_URL').'/t/p/w500' . $poster->file_path;
        }

        return '';
    }

    public function destroy(Request $request)
    {
        $chartId = $request->input('chart_id');

        if (!$chartId) {
            return response()->json(['success' => false, 'message' => 'Chart ID is required'], 400);
        }

        $chart = ImdbChart::where('chart_id', $chartId)->first();

        if (!$chart) {
            return response()->json(['success' => false, 'message' => 'Chart not found'], 404);
        }

        $chart->movies()->delete();
        $chart->delete();

        return response()->json(['success' => true, 'message' => 'Chart deleted successfully']);
    }
}
