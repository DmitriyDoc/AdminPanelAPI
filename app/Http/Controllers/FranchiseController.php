<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use App\Models\TMDb\MoviePoster;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;


class FranchiseController extends Controller
{
    public function index(Request $request, string $slugSect, string $slugFran): array
    {
        $allowedSectionsNames = ['yellow', 'green', 'blue', 'cyan', 'purple', 'black', 'brown', 'red', 'silver', 'tan'];

        if (!in_array($slugSect, $allowedSectionsNames)) {
            return [];
        }

        $currentLocale = App::getLocale();
        $currentLocaleLabel = 'label_' . $currentLocale;
        $titleFieldName = transformTitleByLocale();

        $franchise = LocalizingFranchise::where('value', $slugFran)->first(['id', $currentLocaleLabel]);
        if (!$franchise) {
            return [];
        }

        $collectionTitle = $franchise->{$currentLocaleLabel};
        $franchiseId = $franchise->id;

        $movieIds = CollectionsCategoriesPivot::where('franchise_id', $franchiseId)
            ->pluck('id_movie')
            ->unique()
            ->toArray();

        if (empty($movieIds)) {
            return $this->buildEmptyFranchiseResponse($collectionTitle, $currentLocale, 0);
        }

        $model = modelByName('MovieInfo');
        $allowedFilterFields = $model->getFillable();

        $query = $model->select('id_movie', 'type_film', $titleFieldName, 'original_title', 'published', 'year_release', 'created_at', 'updated_at')
            ->whereIn('id_movie', $movieIds)
            ->with(['assignPoster', 'categories']);

        if ($searchQuery = $request->query('search')) {
            $safeQuery = trim(strtolower(strip_tags($searchQuery)));
            $query->where(function ($q) use ($safeQuery, $allowedFilterFields, $titleFieldName) {
                $q->where($titleFieldName, 'like', '%' . $safeQuery . '%')
                    ->orWhere($allowedFilterFields[1] ?? 'original_title', 'like', '%' . $safeQuery . '%')
                    ->orWhere($allowedFilterFields[3] ?? 'year_release', 'like', '%' . $safeQuery . '%');
            });
        }

        $limit = (int) $request->query('limit', 50);
        $sortDir = in_array(strtolower($request->query('spin', 'asc')), ['asc', 'desc']) ? strtolower($request->query('spin', 'asc')) : 'asc';
        $sortBy = $request->query('orderBy', 'updated_at');

        if (!in_array($sortBy, $allowedFilterFields)) {
            $sortBy = 'updated_at';
        }

        $paginatedMovies = $query->orderBy($sortBy, $sortDir)->paginate($limit);
        $movieItems = $paginatedMovies->items();

        if (empty($movieItems)) {
            return $this->buildEmptyFranchiseResponse($collectionTitle, $currentLocale, 0);
        }

        $movieIdsOnPage = array_column($movieItems, 'id_movie');

        $assignField = 'id_poster_original';

        $assignedPosterIds = [];
        foreach ($movieItems as $item) {
            if (!empty($item->assignPoster->$assignField)) {
                $assignedPosterIds[] = $item->assignPoster->$assignField;
            }
        }

        $assignedPostersMap = [];
        if (!empty($assignedPosterIds)) {
            $assignedPosters = MoviePoster::whereIn('id', $assignedPosterIds)->get();
            foreach ($assignedPosters as $poster) {
                $assignedPostersMap[$poster->id] = $poster;
            }
        }

        $posterSourceMap = [];

        foreach ($movieItems as $item) {
            $posterSourceMap[$item->id_movie] = '-';
        }

        if (!empty($assignedPosterIds)) {
            $tmdbPairs = MoviePoster::whereIn('id', $assignedPosterIds)
                ->pluck('id_movie', 'id')
                ->toArray();

            foreach ($movieItems as $item) {
                $assignedId = $item->assignPoster->id_poster_original ?? null;
                if ($assignedId && isset($tmdbPairs[$assignedId])) {
                    if ((string)$tmdbPairs[$assignedId] === (string)$item->id_movie) {
                        $posterSourceMap[$item->id_movie] = 'tmdb';
                    }
                }
            }
        }

        $imdbChecksByType = [];
        foreach ($movieItems as $item) {
            if ($posterSourceMap[$item->id_movie] === '-') {
                $assignedId = $item->assignPoster->id_poster_original ?? null;
                if ($assignedId) {
                    $typeSlug = getTableSegmentOrTypeId($item->type_film);
                    if ($typeSlug) {
                        if (!isset($imdbChecksByType[$typeSlug])) {
                            $imdbChecksByType[$typeSlug] = [];
                        }
                        $imdbChecksByType[$typeSlug][$assignedId] = $item->id_movie;
                    }
                }
            }
        }

        foreach ($imdbChecksByType as $typeSlug => $expectedPairs) {
            $modelName = 'App\\Models\\Posters' . ucfirst($typeSlug);
            if (class_exists($modelName)) {
                $posterIdsToCheck = array_keys($expectedPairs);
                $foundLegacyPairs = $modelName::whereIn('id', $posterIdsToCheck)
                    ->pluck('id_movie', 'id')
                    ->toArray();

                foreach ($foundLegacyPairs as $foundPosterId => $foundMovieId) {
                    if (isset($expectedPairs[$foundPosterId]) &&
                        (string)$expectedPairs[$foundPosterId] === (string)$foundMovieId) {
                        $posterSourceMap[$foundMovieId] = 'imdb';
                    }
                }
            }
        }
        $localePosters = MoviePoster::whereIn('id_movie', $movieIdsOnPage)
            ->where('locale', $currentLocale)
            ->get()
            ->groupBy('id_movie');

        $legacyPosterIdsByType = [];
        foreach ($movieItems as $item) {
            $typeSlug = getTableSegmentOrTypeId($item->type_film);
            if ($typeSlug) {
                $legacyPosterIdsByType[$typeSlug][] = $item->id_movie;
            }
        }

        $legacyPostersMap = [];
        foreach ($legacyPosterIdsByType as $typeSlug => $ids) {
            $modelName = 'App\\Models\\Posters' . ucfirst($typeSlug);
            if (class_exists($modelName)) {
                $posters = $modelName::whereIn('id_movie', $ids)->get(['id_movie', 'srcset'])->toArray();
                foreach ($posters as $p) {
                    if (!isset($legacyPostersMap[$p['id_movie']])) {
                        $legacyPostersMap[$p['id_movie']] = $p['srcset'];
                    }
                }
            }
        }

        $responseData = [];

        foreach ($movieItems as $item) {
            $posterUrl = $this->getPosterUrlForFranchises(
                $item->id_movie,
                $item,
                $assignField,
                $assignedPostersMap,
                $localePosters,
                $legacyPostersMap
            );

            $typeKey = getTableSegmentOrTypeId($item->type_film);

            $responseData[] = [
                'id_movie'       => $item->id_movie,
                'type_film'      => __('movies.type_movies.' . $typeKey) ?? '',
                'type_film_link' => $typeKey,
                'title'          => $item->{$titleFieldName} ?? $item->original_title ?? '',
                'year'           => $item->year_release,
                'published'      => statusSelection($item->published) ?? [],
                'created_at'     => $item->created_at ? date('Y-m-d', strtotime($item->created_at)) : '',
                'updated_at'     => $item->updated_at ? date('Y-m-d', strtotime($item->updated_at)) : '',
                'poster'         => $posterUrl,
                'poster_source'  => $posterSourceMap[$item->id_movie] ?? '-',
            ];
        }

        return [
            'data'   => $responseData,
            'title'  => $collectionTitle,
            'total'  => $paginatedMovies->total(),
            'locale' => LanguageController::localizingFranchisesList(),
        ];
    }
    private function getPosterUrlForFranchises(string $idMovie, object $item, string $assignField, array $assignedPostersMap, $localePosters, array $legacyPostersMap): string
    {
        // ПРИОРИТЕТ 1: Назначенный постер (из предварительно загруженного мапа)
        $assignedId = $item->assignPoster->$assignField ?? null;

        if ($assignedId && isset($assignedPostersMap[$assignedId])) {
            $poster = $assignedPostersMap[$assignedId];

            if ($poster->id_movie == $idMovie) {
                return $poster->display_url;
            }
        }

        // ПРИОРИТЕТ 2: Любой доступный постер TMDB для этого фильма и локали
        // $localePosters - это коллекция, сгруппированная по id_movie в контроллере
        if ($localePosters->has($idMovie) && $localePosters[$idMovie]->isNotEmpty()) {

            return $localePosters[$idMovie]->first()->display_url;
        }

        // ПРИОРИТЕТ 3: Legacy постер (IMDb) из предварительно загруженного мапа
        if (isset($legacyPostersMap[$idMovie])) {
            $img = explode(',', $legacyPostersMap[$idMovie]);
            if (!empty($img[0])) {
                return trimAfterImageExtension($img[0]) ?? '';
            }
        }

        return '';
    }
    private function buildEmptyFranchiseResponse(string $title, string $currentLocale, int $total = 0): array
    {
        return [
            'data'   => [],
            'title'  => $title,
            'total'  => $total,
            'locale' => LanguageController::localizingFranchisesList(),
        ];
    }
    public function list(Request $request): array
    {
        $model = convertVariableToModelName('LocalizingFranchise','', ['App', 'Models']);
        $franchiseCollection =  $model->all();
        if ($franchiseCollection->isNotEmpty()){
            $allowedFilterFields = $model->getFillable();
            if ($request->has('search')){
                $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
                if (!empty($searchQuery)){
                    $franchiseCollection = $franchiseCollection->where('value',$searchQuery);
                }
            }
            $itemsCount = $franchiseCollection->count()??0;
            $allowedSortFields = ['desc','asc'];
            $limit = $request->query('limit',50);
            $page = $request->query('page',1);
            $sortDir = strtolower($request->query('spin','asc'));
            $sortBy = $request->query('orderBy','created_at');
            if (!in_array($sortBy,$allowedFilterFields)){
                $sortBy = $allowedSortFields[0];
            }
            if (in_array($sortDir,$allowedSortFields)){
                $sortDir == 'desc' ? $franchiseCollection = $franchiseCollection->sortByDesc($sortBy) : $franchiseCollection->sortBy($sortBy);
            }
            $collectionSort = $franchiseCollection->forPage($page,$limit);
            $collectionSortArr = $collectionSort->values()->toArray();
            return [
                'total' => $itemsCount,
                'data' => $collectionSortArr,
                'locale' => LanguageController::localizingFranchisesInfoList()
            ];
        }
        return [];
    }
    public function destroy(Request $request)
    {
        $data = Validator::make($request->all(),[
            'id' => 'required|int',
        ])->safe()->all();
        if (!empty($data)){
            transaction( function () use ($data){
                CollectionsCategoriesPivot::where('franchise_id',$data['id'])->update([
                    'franchise_id'=>null
                ]);
                CollectionsFranchisesPivot::find($data['id'])->delete();
                LocalizingFranchise::find($data['id'])->delete();
            });
        }
    }
}
