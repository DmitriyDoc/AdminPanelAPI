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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class CollectionsController extends Controller
{
    public function index(Request $request, string $slugSect, string $slugColl): array
    {
        $allowedSectionsNames = ['yellow', 'green', 'blue', 'cyan', 'purple', 'black', 'brown', 'red', 'silver', 'tan'];

        if (!in_array($slugSect, $allowedSectionsNames)) {
            return [];
        }

        $currentLocale = App::getLocale();
        $currentLocaleLabel = 'label_' . $currentLocale;

        $section = Category::where('value', $slugSect)->first();
        if (!$section) {
            return [];
        }

        $collection = Collection::where('category_id', $section->id)
            ->where('value', $slugColl)
            ->with('children')
            ->first();

        if (!$collection) {
            return [];
        }

        $collectionId = $collection->id;
        $collectionTitle = $collection->{$currentLocaleLabel};
        $collectionFranchise = $collection->children;

        $movieIds = CollectionsCategoriesPivot::where('collection_id', $collectionId)
            ->pluck('id_movie')
            ->unique()
            ->toArray();

        if (empty($movieIds)) {
            return $this->buildEmptyCollectionResponse($collectionTitle, $currentLocale);
        }

        $model = modelByName('MovieInfo');
        $titleFieldName = transformTitleByLocale();
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
            return $this->buildEmptyCollectionResponse($collectionTitle, $currentLocale, 0, $collectionFranchise);
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
            $tmdbPairs = MoviePoster::whereIn('id', $assignedPosterIds)->pluck('id_movie', 'id')->toArray();
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
                $foundLegacyPairs = $modelName::whereIn('id', $posterIdsToCheck)->pluck('id_movie', 'id')->toArray();

                foreach ($foundLegacyPairs as $foundPosterId => $foundMovieId) {
                    if (isset($expectedPairs[$foundPosterId]) && (string)$expectedPairs[$foundPosterId] === (string)$foundMovieId) {
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

        $franchiseMap = [];
        if (!empty($collectionFranchise)) {
            $franchiseIds = collect($collectionFranchise)->pluck('id')->toArray();
            $franchises = LocalizingFranchise::whereIn('id', $franchiseIds)->get()->keyBy('id');
            foreach ($franchises as $fr) {
                $franchiseMap[$fr->id] = [
                    'label' => $fr->{$currentLocaleLabel},
                    'value' => $fr->value,
                ];
            }
        }

        $responseData = [];
        $uniqueFranchises = [];

        foreach ($movieItems as $item) {
            $posterUrl = $this->getPosterUrlForCollections($item->id_movie, $item, $assignField, $assignedPostersMap, $localePosters, $legacyPostersMap);

            $movieFranchises = [];
            foreach ($item->categories as $cat) {
                if (!empty($cat->franchise_id) && isset($franchiseMap[$cat->franchise_id])) {
                    $frData = $franchiseMap[$cat->franchise_id];
                    $movieFranchises[$frData['value']] = $frData;
                    $uniqueFranchises[$frData['value']] = $frData;
                }
            }

            $responseData[] = [
                'id_movie'      => $item->id_movie,
                'type_film'     => getTableSegmentOrTypeId($item->type_film),
                'title'         => $item->{$titleFieldName} ?? $item->original_title ?? '',
                'year'          => $item->year_release,
                'published'     => statusSelection($item->published) ?? [],
                'created_at'    => $item->created_at ? date('Y-m-d', strtotime($item->created_at)) : '',
                'updated_at'    => $item->updated_at ? date('Y-m-d', strtotime($item->updated_at)) : '',
                'poster'        => $posterUrl,
                'poster_source' => $posterSourceMap[$item->id_movie] ?? '-',
                'franchise'     => array_values($movieFranchises),
            ];
        }

        return [
            'data'      => $responseData,
            'franchise' => array_values($uniqueFranchises),
            'title'     => $collectionTitle,
            'total'     => $paginatedMovies->total(),
            'locale'    => LanguageController::localizingCollectionsList(),
        ];
    }
    private function getPosterUrlForCollections(string $idMovie, object $item, string $assignField, array $assignedPostersMap, $localePosters, array $legacyPostersMap): string
    {
        // ПРИОРИТЕТ 1: Назначенный постер (из предварительно загруженного мапа)
        $assignedId = $item->assignPoster->$assignField ?? null;

        if ($assignedId && isset($assignedPostersMap[$assignedId])) {
            $poster = $assignedPostersMap[$assignedId];

            if ((string)$poster->id_movie === (string)$idMovie) {
                return $poster->display_url;
            }
        }

        // ПРИОРИТЕТ 2: Любой доступный постер TMDB для этого фильма и локали
        // $localePosters уже отфильтрована в контроллере по id_movie, поэтому совпадение гарантировано
        if ($localePosters->has($idMovie) && $localePosters[$idMovie]->isNotEmpty()) {
            return $localePosters[$idMovie]->first()->display_url;
        }

        // ПРИОРИТЕТ 3: Legacy постер (IMDb) из предварительно загруженного мапа
        // $legacyPostersMap также уже отфильтрован по id_movie в контроллере
        if (isset($legacyPostersMap[$idMovie])) {
            $img = explode(',', $legacyPostersMap[$idMovie]);
            if (!empty($img[0])) {
                return trimAfterImageExtension($img[0]) ?? '';
            }
        }

        return '';
    }
    private function buildEmptyCollectionResponse(string $title, string $currentLocale, int $total = 0, $collectionFranchise = null): array
    {
        $uniqueFranchises = [];
        if ($collectionFranchise) {
            foreach ($collectionFranchise as $fr) {
                $uniqueFranchises[$fr->value] = [
                    'label' => $fr->{'label_' . $currentLocale},
                    'value' => $fr->value,
                ];
            }
        }

        return [
            'data'      => [],
            'franchise' => array_values($uniqueFranchises),
            'title'     => $title,
            'total'     => $total,
            'locale'    => LanguageController::localizingCollectionsList(),
        ];
    }
    public function updateCollection (Request $request) : array
    {
        $data = Validator::make($request->all(),[
            'id' => 'required|int',
            'category_id' => 'required|int',
            'value' => '|string|max:30',
            'label_en' => 'required|string|max:40',
            'label_ru' => 'required|string|max:40',
        ])->safe()->all();
        if (!empty($data['id'])){
            try {
                Collection::where('id',$data['id'])->update([
                    'category_id' => $data['category_id'],
                    'value' => $data['value'],
                    'label_en' => $data['label_en'],
                    'label_ru' => $data['label_ru']
                ]);
                return [
                    'success' => true,
                ];
            } catch (\Exception $e) {
                Log::error("Error saving poster for collection Error: {$e->getMessage()}");
            }

        }
        return [
            'success' => false,
        ];
    }
    public function updateCollectionImages(Request $request)
    {
        $request->validate([
            'collections.*.cover' => 'required|image|mimes:jpeg,png,gif|max:10240',
        ]);

        $collectionsFiles = $request->file('collections') ?? [];

        if (empty($collectionsFiles)) {
            Log::error('No collections files received');
            return response()->json(['success' => false, 'message' => 'Нет данных для обработки (файлы отсутствуют).'], 422);
        }

        foreach ($collectionsFiles as $collectionId => $data) {
            if (!isset($data['cover'])) {
                Log::error("Missing required cover file for collection {$collectionId}");
                return response()->json(['success' => false, 'message' => "Отсутствует обязательный файл cover для коллекции {$collectionId}"], 422);
            }

            $coverPath = "collections/{$collectionId}/cover";
            if (Storage::disk('public')->exists($coverPath)) {
                Storage::disk('public')->deleteDirectory($coverPath);
            }
            $coverFile = $data['cover'];
            $coverFilename = $coverFile->getClientOriginalName();
            $coverFile->storeAs($coverPath, $coverFilename, 'public');
        }

        return response()->json(['success' => true]);
    }
    public function collectionImages()
    {
        $collectionsDir = 'collections';
        $result = [];
        $collectionDirs = Storage::disk('public')->directories($collectionsDir);

        foreach ($collectionDirs as $collectionDir) {
            $collectionId = basename($collectionDir);
            $result[$collectionId] = [
                'cover' => '',
            ];

            $coverPath = "collections/{$collectionId}/cover";
            $files = Storage::disk('public')->files($coverPath);

            if (!empty($files)) {
                $url = Storage::disk('public')->url($files[0]);
                $result[$collectionId]['cover'] = $url;
            }
        }

        return response()->json($result);
    }
    public function deleteCollectionImage(Request $request)
    {
        $data = $request->validate([
            'collectionId' => 'required|integer',
            'file' => 'required|string',
        ]);

        $path = "collections/{$data['collectionId']}/cover/{$data['file']}";

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            Log::info("Deleted cover image: {$path}");
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Файл не найден'], 404);
    }
    public function list(Request $request): array
    {
        $model = convertVariableToModelName('Collection','', ['App', 'Models']);
        $CollectionCol =  $model->all();
        if ($CollectionCol->isNotEmpty()){
            $allowedFilterFields = $model->getFillable();
            if ($request->has('search')){
                $searchQuery = trim(strtolower(strip_tags($request->query('search'))));
                if (!empty($searchQuery)){
                    $CollectionCol = $CollectionCol->where('value',$searchQuery);
                }
            }
            $itemsCount = $CollectionCol->count()??0;
            $allowedSortFields = ['desc','asc'];
            $limit = $request->query('limit',20);
            $page = $request->query('page',1);
            $sortDir = strtolower($request->query('spin','asc'));

            $sortBy = $request->query('orderBy','id');
            if (!in_array($sortBy,$allowedFilterFields)){
                $sortBy = $allowedSortFields[0];
            }
            if (in_array($sortDir,$allowedSortFields)){
                $sortDir == 'desc' ? $CollectionCol = $CollectionCol->sortByDesc($sortBy) : $CollectionCol->sortBy($sortBy);
            }

            $collectionSort = $CollectionCol->forPage($page,$limit);
            $collectionSortArr = $collectionSort->values()->toArray();
            return [
                'total' => $itemsCount,
                'data' => $collectionSortArr,
                'locale' => LanguageController::localizingCollectionsInfoList()
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
                CollectionsCategoriesPivot::where('collection_id',$data['id'])->delete();
                CollectionsFranchisesPivot::where('collection_id',$data['id'])->delete();
                Collection::find($data['id'])->delete();
            });
        }
    }
}
