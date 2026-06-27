<?php

namespace App\Http\Controllers;

use App\Models\ImdbChart;
use App\Models\ImdbChartMovie;
use App\Models\AssignPoster;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

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

        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'updated_at';
        }
        if (!in_array($sortDir, $allowedSortDirections)) {
            $sortDir = 'desc';
        }

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
                $charts['data'][$key]['last_parsed_at'] = !empty($item['last_parsed_at'])
                    ? date('Y-m-d', strtotime($item['last_parsed_at'])) : '';
                $charts['data'][$key]['updated_at'] = !empty($item['updated_at'])
                    ? date('Y-m-d', strtotime($item['updated_at'])) : '';
                $charts['data'][$key]['created_at'] = !empty($item['created_at'])
                    ? date('Y-m-d', strtotime($item['created_at'])) : '';
            }
        }

        $charts['locale'] = LanguageController::localizingChartsList();

        return $charts;
    }

    /**
     * Получить список фильмов из конкретного чарта с динамическим определением типа фильма
     */
    public function showMovies(Request $request, string $chartId): array
    {
        $locale = app()->getLocale();
        $titleField = transformTitleByLocale();

        $allowedSortFields = ['position', 'published', 'year_release', 'created_at', 'updated_at'];
        $allowedSortDirections = ['desc', 'asc'];

        $limit = $request->query('limit', 50);
        $sortDir = strtolower($request->query('spin', 'asc'));
        $sortBy = $request->query('orderBy', 'position');

        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'position';
        }
        if (!in_array($sortDir, $allowedSortDirections)) {
            $sortDir = 'asc';
        }

        $chart = ImdbChart::where('chart_id', $chartId)->first();

        if (!$chart) {
            return [
                'data' => [],
                'total' => 0,
                'locale' => LanguageController::localizingChartMoviesList(),
                'chart_info' => null,
                'error' => 'Chart not found'
            ];
        }

        $chartMovies = ImdbChartMovie::where('chart_id', $chartId)
            ->orderBy('position', 'asc')
            ->get()
            ->keyBy('movie_id');

        $movieIds = $chartMovies->keys()->toArray();

        if (empty($movieIds)) {
            return [
                'data' => [],
                'total' => 0,
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
                $q->where($allowedFilterFields[4], 'like', '%' . $safeQuery . '%')
                    ->orWhere($allowedFilterFields[5], 'like', '%' . $safeQuery . '%')
                    ->orWhere($allowedFilterFields[1], 'like', '%' . $safeQuery . '%');
            });
        }

        $allMovies = $query->get();

        // Добавляем position из чарта для сортировки
        foreach ($allMovies as $movie) {
            $movie->position = $chartMovies[$movie->id_movie]->position ?? 999999;
        }

        // Сортировка
        if ($sortBy === 'position') {
            $allMovies = $allMovies->sortBy('position', SORT_REGULAR, $sortDir === 'desc');
        } else {
            $allMovies = $allMovies->sortBy($sortBy, SORT_REGULAR, $sortDir === 'desc');
        }

        $total = $allMovies->count();
        $offset = ($request->query('page', 1) - 1) * $limit;
        $paginatedMovies = $allMovies->slice($offset, $limit)->values();

        $moviesData = [];
        $apiService = new ApiRequestImages();

        if ($paginatedMovies->isNotEmpty()) {
            foreach ($paginatedMovies as $item) {
                $typeMovie = $item->type_film;
                $typeMovieSlug = getTableSegmentOrTypeId($typeMovie); // например 'FeatureFilm'
                $modelPosterName = 'poster' . $typeMovieSlug;          // 'posterFeatureFilm'
                $relationName = camelToSnake($modelPosterName);        // 'poster_feature_film'

                // 🔑 КЛЮЧЕВОЙ МОМЕНТ: загружаем связь и конвертируем в массив
                $item->load($modelPosterName);
                $itemArr = $item->toArray();

                // Теперь $itemArr[$relationName] — это массив, как в index
                $posterSrcSet = $itemArr[$relationName][0]['srcset'] ?? '';

                // Проверяем назначенный постер (AssignPoster)
                $assignPosterId = AssignPoster::where('id_movie', $item->id_movie)->first('id_poster_original');
                if (!empty($assignPosterId) && !empty($itemArr[$relationName])) {
                    foreach ($itemArr[$relationName] as $p) {
                        if ($p['id'] == $assignPosterId->id_poster_original) {
                            $posterSrcSet = $p['srcset'] ?? '';
                            break;
                        }
                    }
                }

                $img = explode(',', $posterSrcSet ?? '');
                // 🔑 Используем trimAfterImageExtension как в index
                $poster = trimAfterImageExtension($img[0] ?? '') ?? '';

                // API запрос для получения актуального постера
                $hasher = new IdHasher($item->id_movie);
                $hashDecodeId = $hasher->getResult();
                $posterApi = $apiService->sendApiRequest(env('API_HOST_URL') . "/api/images/{$typeMovieSlug}/{$hashDecodeId}/original_poster");

                $mediaPosterApi = null;
                if (isset($posterApi['status']) && $posterApi['status'] === 200 && ($posterApi['data']['success'] ?? false)) {
                    $mediaPosterApi = $posterApi['data']['images']['medium'][0]['url'] ?? null;
                }

                // API имеет приоритет, иначе — локальный постер
                $poster = $mediaPosterApi ?? $poster;

                $moviesData[] = [
                    'id_movie' => $item->id_movie,
                    'title' => $itemArr[$titleField] ?? $itemArr['original_title'] ?? '',
                    'published' => statusSelection($itemArr['published']) ?? [],
                    'year_release' => $itemArr['year_release'],
                    'type_film' => $itemArr['type_film'],
                    'type_film_string' => getTableSegmentOrTypeId($itemArr['type_film']),
                    'created_at' => !empty($itemArr['created_at']) ? date('Y-m-d', strtotime($itemArr['created_at'])) : '',
                    'updated_at' => !empty($itemArr['updated_at']) ? date('Y-m-d', strtotime($itemArr['updated_at'])) : '',
                    'position' => $item->position,
                    'poster' => $poster,
                ];
            }
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
