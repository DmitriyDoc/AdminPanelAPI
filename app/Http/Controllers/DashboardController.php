<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Parser\TMDBImages;
use App\Models\AssignPoster;
use App\Models\MovieInfo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Events\DashboardCurrentPercentageEvent;
use App\Models\CollectionsCategoriesPivot;
use App\Models\Collection;
use App\Models\Category;
use PHPUnit\Exception;

class DashboardController
{
    private $dashboardCountTable = 9;

    public function index()
    {
        $allowedTableNames = [
            0 => 'FeatureFilm',
            1 => 'MiniSeries',
            2 => 'ShortFilm',
            3 => 'TvMovie',
            4 => 'TvSeries',
            5 => 'TvShort',
            6 => 'TvSpecial',
            7 => 'Video',
            8 => 'Celebs',
        ];

        $dateYesterday = Carbon::now()->subDays(1);

        // 1. Общая статистика из movies_info
        $movieStats = DB::table('movies_info')
            ->selectRaw('type_film, COUNT(*) as total_count, SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_count')
            ->addBinding($dateYesterday)
            ->whereIn('type_film', array_keys($allowedTableNames))
            ->groupBy('type_film')
            ->get()
            ->keyBy('type_film');

        // 2. Опубликованные (с постерами) из assign_posters
        $publishedStats = DB::table('assign_posters')
            ->selectRaw('type_film, COUNT(*) as published_count')
            ->whereIn('type_film', array_keys($allowedTableNames))
            ->groupBy('type_film')
            ->get()
            ->keyBy('type_film');

        // 3. Считаем общую сумму опубликованных по всем типам
        $totalPublished = $publishedStats->sum('published_count');

        // 4. Статистика по знаменитостям
        $celebsModel = modelByName('CelebsInfo');
        $celebsTotal = $celebsModel->count();
        $celebsNew = $celebsModel->where('created_at', '>=', $dateYesterday)->count();

        $data = [];

        foreach ($allowedTableNames as $index => $name) {
            if ($name === 'Celebs') {
                $count = $celebsTotal;
                $update = $celebsNew;
                $published = null;
            } else {
                $typeId = getTableSegmentOrTypeId($name);

                $movieStat = $movieStats->get($typeId);
                $count = $movieStat ? $movieStat->total_count : 0;
                $update = $movieStat ? $movieStat->new_count : 0;

                $pubStat = $publishedStats->get($typeId);
                $published = $pubStat ? $pubStat->published_count : 0;
            }

            $data['data'][$index]['key'] = $index . '_' . $name;
            $data['data'][$index]['title'] = $name !== 'Celebs'
                ? __('movies.type_movies.' . $name)
                : __('movies.celebs');

            $data['data'][$index]['count'] = $count;
            $data['data'][$index]['lastAddCount'] = $update;
            $data['data'][$index]['published'] = $published;

            $progressBarTotal = ceil((($index + 1) * 100) / $this->dashboardCountTable);
            event(new DashboardCurrentPercentageEvent($progressBarTotal));

        }

        if (!empty($data)) {
            $data['total_published'] = $totalPublished;
            $data['locale'] = LanguageController::localizingDashboardInfo();
        }

        return $data;
    }

    public function graphByCollections()
    {
        $locale = app()->getLocale();
        $labelField = $locale === 'ru' ? 'label_ru' : 'label_en';
        $titleField = $locale === 'ru' ? 'title_ru' : 'title_en';

        // 1. Получаем все опубликованные ID фильмов
        $publishedMovieIds = DB::table('assign_posters')
            ->whereIn('type_film', [1,2,3,4,5,6,7,8])
            ->pluck('id_movie');

        if ($publishedMovieIds->isEmpty()) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        // 2. Основной запрос: Категории -> Коллекции -> Count
        $data = DB::table('collections_categories_pivot as ccp')
            ->join('collections as col', 'col.id', '=', 'ccp.collection_id')
            ->join('categories as c', 'c.id', '=', 'col.category_id')
            ->whereIn('ccp.id_movie', $publishedMovieIds)
            ->selectRaw("c.id as category_id, c.{$titleField} as category_name, c.value as category_color")
            ->selectRaw("col.id as collection_id, col.value as collection_value, col.{$labelField} as collection_label")
            ->selectRaw("COUNT(DISTINCT ccp.id_movie) as movies_count")
            ->groupBy('col.id', 'c.id', "c.{$titleField}", "c.value", "col.{$labelField}", 'col.value')
            ->orderBy('c.id')
            ->orderBy('movies_count', 'desc')
            ->get();

        // 3. Считаем "Без категории" для полноты картины
        $withCollectionCount = DB::table('collections_categories_pivot')
            ->whereIn('id_movie', $publishedMovieIds)
            ->distinct('id_movie')
            ->count('id_movie');

        $uncategorizedCount = count($publishedMovieIds) - $withCollectionCount;

        // 4. Группировка данных для фронтенда
        $grouped = $data->groupBy('category_id')->map(function($items) {
            $first = $items->first();

            // ВАЖНО: оборачиваем collections в collect() после filter()
            $collections = collect($items->map(fn($item) => [
                'id' => $item->collection_id,
                'label' => $item->collection_label,
                'count' => (int) $item->movies_count
            ])->values()->filter(fn($c) => $c['count'] > 0)->values());

            return [
                'id' => $first->category_id,
                'name' => $first->category_name,
                'color_key' => $first->category_color ?? 'gray',
                'collections' => $collections
            ];
        })->values();

        // Если есть неучтенные, добавляем серую категорию
        if ($uncategorizedCount > 0) {
            $grouped->push([
                'id' => 999,
                'name' => __('dashboard.uncategorized') ?? 'Прочее',
                'color_key' => 'gray',
                'collections' => collect([[
                    'id' => 0,
                    'label' => 'Не распределено',
                    'count' => $uncategorizedCount
                ]])
            ]);
        }

        // Общая сумма для проверки
        // Исправлено: гарантируем работу с коллекцией
        $totalInGraph = collect($grouped)->sum(fn($cat) =>
        collect($cat['collections'])->sum('count')
        );

        return response()->json([
            'data' => $grouped,
            'meta' => [
                'total_published' => count($publishedMovieIds),
                'total_in_graph' => $totalInGraph
            ]
        ]);
    }
    public function graphByYears()
    {
        $locale = app()->getLocale();

        // 1. Получаем все опубликованные фильмы (только published=2)
        $publishedMovies = MovieInfo::where('published', 2)
            ->whereNotNull('year_release')
            ->select('id_movie', 'year_release', 'rating')
            ->get();

        if ($publishedMovies->isEmpty()) {
            return response()->json(['data' => [], 'meta' => ['total_in_graph' => 0, 'total_published' => 0]]);
        }

        // 2. Группировка по годам
        $groupedByYear = $publishedMovies->groupBy('year_release')->map(function($movies) {
            return [
                'total' => $movies->count(),
                'ratings' => $movies->pluck('rating')->filter()->toArray() // убираем NULL
            ];
        })->sortKeys(descending: true); // годы по возрастанию

        // 3. Подготовка данных для режима "по количеству"
        $byCount = $groupedByYear->map(function($data, $year) {
            return [
                'id' => $year,
                'name' => (string) $year,
                'color_key' => 'blue', // фиксированный цвет для режима количества
                'collections' => [[
                    'id' => "count-{$year}",
                    'label' => 'Фильмов',
                    'count' => $data['total']
                ]]
            ];
        })->values();

        // 4. Подготовка данных для режима "по рейтингу"
        // Собираем все рейтинги для определения диапазона
        $allRatings = $publishedMovies->pluck('rating')->filter();
        $minRating = $allRatings->min() ?? 0;
        $maxRating = $allRatings->max() ?? 10;

        // Создаем бакеты рейтингов (шаг 2.0: 0-2, 2-4, ..., 8-10)
        $ratingBuckets = [];
        for ($i = floor($minRating / 2) * 2; $i < ceil($maxRating / 2) * 2; $i += 2) {
            $ratingBuckets[] = ['min' => $i, 'max' => $i + 2, 'label' => "{$i}–" . ($i + 2)];
        }

        // Цветовая схема для рейтингов (низкий → высокий)
        $ratingColors = ['red', 'orange', 'tan', 'yellow', 'green'];

        $byRating = $groupedByYear->map(function($data, $year) use ($ratingBuckets, $ratingColors) {
            $collections = [];

            foreach ($ratingBuckets as $idx => $bucket) {
                $count = collect($data['ratings'])->filter(fn($r) => $r >= $bucket['min'] && $r < $bucket['max'])->count();
                if ($count > 0) {
                    $collections[] = [
                        'id' => "rating-{$bucket['min']}-{$bucket['max']}",
                        'label' => $bucket['label'],
                        'count' => $count,
                        'color_key' => $ratingColors[min($idx, count($ratingColors) - 1)]
                    ];
                }
            }

            // Если в году есть фильмы, но все рейтинги NULL — добавляем "Без рейтинга"
            if (empty($collections) && $data['total'] > 0) {
                $collections[] = [
                    'id' => 'rating-null',
                    'label' => 'Без рейтинга',
                    'count' => $data['total'],
                    'color_key' => 'gray'
                ];
            }

            return [
                'id' => $year,
                'name' => (string) $year,
                'color_key' => 'blue', // базовый цвет, переопределяется в collections
                'collections' => collect($collections)->sortByDesc('count')->values()
            ];
        })->values()->filter(fn($y) => $y['collections']->isNotEmpty());

        // 5. Мета-данные
        $totalInGraph = $byCount->sum(fn($y) => $y['collections'][0]['count']);

        return response()->json([
            'data' => [
                'by_count' => $byCount,
                'by_rating' => $byRating,
                'rating_range' => ['min' => $minRating, 'max' => $maxRating]
            ],
            'meta' => [
                'total_published' => $publishedMovies->count(),
                'total_in_graph' => $totalInGraph
            ]
        ]);
    }
    public function test()
    {

    }
}
