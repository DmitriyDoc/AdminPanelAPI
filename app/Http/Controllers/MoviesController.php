<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\MovieInfo;

use App\Models\MoviesInfoEn;
use App\Models\MoviesInfoRu;
use App\Models\TagsMoviesPivot;

use App\Models\TMDb\MoviePoster;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class MoviesController extends Controller
{
    // Выносим список таблиц на уровень класса для переиспользования
    private const ALLOWED_TABLE_NAMES = [
        'FeatureFilm',
        'MiniSeries',
        'ShortFilm',
        'TvMovie',
        'TvSeries',
        'TvShort',
        'TvSpecial',
        'Video',
        'Celebs',
    ];
    public function index(Request $request): array
    {
        $tableName = request()->segment(3) ?? '';

        if (!in_array($tableName, self::ALLOWED_TABLE_NAMES)) {
            $tableName = self::ALLOWED_TABLE_NAMES[0];
        }

        $model = modelByName('MovieInfo');
        $modelPosterName = 'poster' . $tableName;
        $relationName = camelToSnake($modelPosterName);
        $typeId = getTableSegmentOrTypeId($tableName);
        $allowedSortDirections = ['desc', 'asc'];
        $allowedFilterFields = $model->getFillable();
        $titleFieldName = transformTitleByLocale();

        $model = $model->select('id_movie', $titleFieldName, 'published', 'year_release', 'created_at', 'updated_at')
            ->where('type_film', $typeId);

        $limit = $request->query('limit', 50);
        $sortDir = strtolower($request->query('spin', 'desc'));
        $sortBy = $request->query('orderBy', 'updated_at');

        if (!in_array($sortBy, $allowedFilterFields)) {
            $sortBy = 'updated_at';
        }
        if (!in_array($sortDir, $allowedSortDirections)) {
            $sortDir = $allowedSortDirections[0];
        }

        if ($query = $request->query('search')) {
            $safeQuery = trim(strtolower(strip_tags($query)));
            $model = $model->where($allowedFilterFields[4], 'like', '%' . $safeQuery . '%')
                ->orWhere($allowedFilterFields[5], 'like', '%' . $safeQuery . '%')
                ->orWhere($allowedFilterFields[1], 'like', '%' . $safeQuery . '%');
        }

        $modelArr = $model->with([$modelPosterName, 'assignPoster'])
            ->orderBy($sortBy, $sortDir)
            ->paginate($limit)
            ->toArray();

        if (!empty($modelArr['data'])) {
            $assignedPosterIds = [];
            foreach ($modelArr['data'] as $item) {
                $assignedId = $item['assign_poster']['id_poster_original'] ?? null;
                if ($assignedId) {
                    $assignedPosterIds[] = $assignedId;
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
            foreach ($modelArr['data'] as $item) {
                $posterSourceMap[$item['id_movie']] = '-';
            }

            if (!empty($assignedPosterIds)) {
                $tmdbPairs = MoviePoster::whereIn('id', $assignedPosterIds)->pluck('id_movie', 'id')->toArray();

                foreach ($modelArr['data'] as $item) {
                    $assignedId = $item['assign_poster']['id_poster_original'] ?? null;
                    if ($assignedId && isset($tmdbPairs[$assignedId])) {
                        // Проверяем, что id_movie в таблице совпадает с id_movie текущего элемента
                        if ((string)$tmdbPairs[$assignedId] === (string)$item['id_movie']) {
                            $posterSourceMap[$item['id_movie']] = 'tmdb';
                        }
                    }
                }
            }

            $imdbChecksByType = [];
            foreach ($modelArr['data'] as $item) {
                if ($posterSourceMap[$item['id_movie']] === '-') {
                    $assignedId = $item['assign_poster']['id_poster_original'] ?? null;
                    if ($assignedId) {
                        if (!isset($imdbChecksByType[$tableName])) {
                            $imdbChecksByType[$tableName] = [];
                        }
                        $imdbChecksByType[$tableName][$assignedId] = $item['id_movie'];
                    }
                }
            }

            foreach ($imdbChecksByType as $type => $expectedPairs) {
                $modelName = 'App\\Models\\Posters' . ucfirst($type);
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

            foreach ($modelArr['data'] as $k => $item) {
                $modelArr['data'][$k]['title'] = $item['title'] ?? $item['original_title'];
                $modelArr['data'][$k]['published'] = statusSelection($item['published']) ?? [];
                $modelArr['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                $modelArr['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';

                $posterUrl = $this->getPosterUrlForList(
                    $item['id_movie'],
                    $item,
                    $tableName,
                    $assignedPostersMap,
                    $relationName
                );

                $modelArr['data'][$k]['poster'] = $posterUrl;
                $modelArr['data'][$k]['poster_source'] = $posterSourceMap[$item['id_movie']] ?? '-';

                unset($modelArr['data'][$k][$relationName]);
                unset($modelArr['data'][$k]['assign_poster']);
            }
        }

        $modelArr['locale'] = LanguageController::localizingMoviesList();

        return $modelArr;
    }
    public function showExportMovies(Request $request)
    {
        $model = modelByName('MovieInfo');
        $titleFieldName = transformTitleByLocale();

        $model = $model->select('id_movie', $titleFieldName, 'type_film', 'published', 'year_release', 'created_at', 'updated_at')
            ->where('published', 1)
            ->with('assignPoster');

        $modelArr = $model->orderBy('created_at', 'desc')->paginate(20)->toArray();

        if (!empty($modelArr['data'])) {
            $hashedIds = [];
            $movieTypesMap = [];
            $assignedPosterIds = [];

            foreach ($modelArr['data'] as $item) {
                $hasher = new IdHasher($item['id_movie']);
                $hashedId = $hasher->getResult() ?? '';

                if (!empty($hashedId)) {
                    $hashedIds[$hashedId][] = ['old_id' => $item['id_movie']];
                    $hashedIds['api'][] = $hashedId;
                    $movieTypesMap[$hashedId] = $item['type_film'];
                }

                $assignedId = $item['assign_poster']['id_poster_original'] ?? null;
                if ($assignedId) {
                    $assignedPosterIds[] = $assignedId;
                }
            }

            $apiPreviewLinks = [];
            if (!empty($hashedIds['api'])) {
                $data = [
                    'movieIds' => $hashedIds['api'],
                    'movieTypes' => $movieTypesMap,
                ];

                $apiService = new ApiRequestImages();
                $previewImagesApi = $apiService->sendApiRequest(
                    env('API_HOST_URL') . "/api/images/batch/types/original_poster/small",
                    'POST',
                    $data,
                    true
                );

                if (isset($previewImagesApi['status']) && $previewImagesApi['status'] === 200 && isset($previewImagesApi['data']['success']) && $previewImagesApi['data']['success']) {
                    unset($hashedIds['api']);
                    foreach ($previewImagesApi['data']['images'] as $k => $image) {
                        if (!empty($image)) {
                            $apiPreviewLinks[$k] = $image;
                        }
                    }
                }
            }

            $posterSourceMap = [];
            foreach ($modelArr['data'] as $item) {
                $posterSourceMap[$item['id_movie']] = '-';
            }


            if (!empty($assignedPosterIds)) {
                $tmdbPairs = MoviePoster::whereIn('id', $assignedPosterIds)->pluck('id_movie', 'id')->toArray();
                foreach ($modelArr['data'] as $item) {
                    $assignedId = $item['assign_poster']['id_poster_original'] ?? null;
                    if ($assignedId && isset($tmdbPairs[$assignedId])) {
                        if ((string)$tmdbPairs[$assignedId] === (string)$item['id_movie']) {
                            $posterSourceMap[$item['id_movie']] = 'tmdb';
                        }
                    }
                }
            }

            $imdbChecksByType = [];
            foreach ($modelArr['data'] as $item) {
                if ($posterSourceMap[$item['id_movie']] === '-') {
                    $assignedId = $item['assign_poster']['id_poster_original'] ?? null;
                    if ($assignedId) {
                        $typeSlug = getTableSegmentOrTypeId($item['type_film']);
                        if ($typeSlug) {
                            if (!isset($imdbChecksByType[$typeSlug])) {
                                $imdbChecksByType[$typeSlug] = [];
                            }
                            $imdbChecksByType[$typeSlug][$assignedId] = $item['id_movie'];
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

            foreach ($modelArr['data'] as $k => $item) {
                $modelArr['data'][$k]['title'] = $item[$titleFieldName];
                $modelArr['data'][$k]['id_movie'] = $item['id_movie'];
                $modelArr['data'][$k]['year_release'] = $item['year_release'];
                $modelArr['data'][$k]['published'] = statusSelection($item['published']);
                $modelArr['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at']));
                $modelArr['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at']));
                $modelArr['data'][$k]['poster_source'] = $posterSourceMap[$item['id_movie']] ?? '-';

                foreach ($hashedIds as $hashKey => $ids) {
                    if ($hashKey === 'api') continue;
                    if ($item['id_movie'] == $ids[0]['old_id']) {
                        $modelArr['data'][$k]['poster'] = (!empty($apiPreviewLinks[$hashKey]))
                            ? $apiPreviewLinks[$hashKey][0]['url'] ?? 'Poster error'
                            : 'Poster error';
                    }
                }
            }
        }

        $modelArr['locale'] = LanguageController::localizingMoviesExportList();
        return $modelArr;
    }
    private function getPosterUrlForList(string $idMovie, array $item, string $tableName, array $assignedPostersMap, string $relationName): string
    {
        $assignedId = $item['assign_poster']['id_poster_original'] ?? null;

        // ==========================================
        // ПРИОРИТЕТ 1: Ищем постер в модели MoviePoster по назначенному ID
        // ==========================================
        if ($assignedId && isset($assignedPostersMap[$assignedId])) {
            $poster = $assignedPostersMap[$assignedId];
            if ($poster->id_movie == $idMovie) {
                return $poster->display_url ?? '';
            }
        }

        // ==========================================
        // ПРИОРИТЕТ 2: Запасной вариант - ищем в динамической модели IMDb (PostersFeatureFilm и т.д.)
        // ==========================================
        if ($assignedId) {
            $modelName = "App\\Models\\Posters{$tableName}";

            if (class_exists($modelName)) {
                // Здесь мы ищем по ID в конкретной таблице IMDb, где этот ID точно принадлежит этому фильму
                $imdbPoster = $modelName::where('id',$assignedId)->where('id_movie',$idMovie)->first() ;
                if ($imdbPoster) {
                    return $imdbPoster->id_movie === $idMovie ? $imdbPoster->srcset : $imdbPoster->src;
                }
            }
        }

        // ==========================================
        // ПРИОРИТЕТ 3: Если назначенного постера нет ни там, ни там, берем любой первый доступный MoviePoster для этого фильма
        // ==========================================
        $fallbackPoster = MoviePoster::where('id_movie', $idMovie)->first();
        if ($fallbackPoster) {
            return $fallbackPoster->display_url ?? '';
        }

        // ==========================================
        // ПРИОРИТЕТ 4: Глобальный поиск по ВСЕМ моделям постеров IMDb как резервный вариант
        // Перебираем все разрешенные таблицы, пока не найдем совпадение по id_movie
        // ==========================================
        foreach (self::ALLOWED_TABLE_NAMES as $fallbackTableName) {
            $fallbackModelName = "App\\Models\\Posters{$fallbackTableName}";

            if (class_exists($fallbackModelName)) {
                // Ищем постер, который точно принадлежит этому id_movie в любой из таблиц
                $fallbackPoster = $fallbackModelName::where('id_movie', $idMovie)->first();

                if ($fallbackPoster) {
                    // Если нашли, применяем старую логику парсинга srcset (на случай, если там несколько значений через запятую)
                    $rawUrl = $fallbackPoster->srcset ?? $fallbackPoster->src ?? '';
                    if (!empty($rawUrl)) {
                        $img = explode(',', $rawUrl);
                        return trimAfterImageExtension($img[0]) ?? '';
                    }
                }
            }
        }
        return '';
    }
    public function show(string $id)
    {
        $infoMovieData = [];
        $safeId = trim(strtolower(strip_tags($id)));

        $model = modelByName('MovieInfo');
        $typeMovie = $model->query()->select('type_film')->where('id_movie', $safeId)->first();

        if (empty($typeMovie)) {
            return $infoMovieData;
        }

        $typeMovie = $typeMovie->toArray()['type_film'];
        $modelPosterName = 'poster' . getTableSegmentOrTypeId($typeMovie);
        $titleFieldName = transformTitleByLocale();
        $localazingName = 'localazing' . ucfirst(Lang::locale());
        $currentLocaleLabel = 'label_' . Lang::locale();
        $relationPosterName = camelToSnake($modelPosterName);
        $relationLocalizeName = camelToSnake($localazingName);

        $modelArr = $model::with([$modelPosterName, 'collection', $localazingName])
            ->where('type_film', $typeMovie)
            ->where('id_movie', $safeId)
            ->first();

        if (empty($modelArr)) {
            return $infoMovieData;
        }

        $modelArr = $modelArr->toArray();
        $typeMovieSlug = getTableSegmentOrTypeId($typeMovie);

        $infoMovieData = $modelArr[$relationLocalizeName] ?? [];
        $infoMovieData['type_film'] = __('movies.type_movies.' . $typeMovieSlug);
        $infoMovieData['slug'] = $typeMovieSlug;
        $infoMovieData['title'] = $modelArr[$titleFieldName];
        $infoMovieData['published'] = $modelArr['published'];
        $infoMovieData['published_status'] = statusSelection($modelArr['published']) ?? [];
        $infoMovieData['original_title'] = $modelArr['original_title'];
        $infoMovieData['year_release'] = $modelArr['year_release'];
        $infoMovieData['restrictions'] = $modelArr['restrictions'];
        $infoMovieData['runtime'] = $modelArr['runtime'];
        $infoMovieData['rating'] = $modelArr['rating'];
        $infoMovieData['budget'] = $modelArr['budget'];
        $infoMovieData['genres'] = json_decode($modelArr[$relationLocalizeName]['genres'] ?? null) ?? [];
        $infoMovieData['cast'] = json_decode($modelArr[$relationLocalizeName]['cast'] ?? null) ?? [];
        $infoMovieData['directors'] = json_decode($modelArr[$relationLocalizeName]['directors'] ?? null) ?? [];
        $infoMovieData['writers'] = json_decode($modelArr[$relationLocalizeName]['writers'] ?? null) ?? [];
        $infoMovieData['countries'] = json_decode($modelArr[$relationLocalizeName]['countries'] ?? null) ?? [];
        $infoMovieData['companies'] = (object) unset_serialize_key(unserialize($modelArr['companies'] ?? null)) ?? [];
        $infoMovieData['created_at'] = !empty($modelArr['created_at']) ? date('Y-m-d', strtotime($modelArr['created_at'])) : '';
        $infoMovieData['updated_at'] = !empty($modelArr['updated_at']) ? date('Y-m-d', strtotime($modelArr['updated_at'])) : '';
        $infoMovieData['collection'] = $modelArr['collection'];
        $infoMovieData['locale'] = LanguageController::localizingMovieShow();
        $infoMovieData['poster'] = $this->getPosterUrl($safeId, $typeMovie, $modelArr, $relationPosterName);


        $assignPosterRecord = AssignPoster::where('id_movie', $safeId)->first(['id_poster_original']);
        $infoMovieData['assign_posters'] = !empty($assignPosterRecord);

        $activeSource = 'none';
        if (!empty($assignPosterRecord) && !empty($assignPosterRecord->id_poster_original)) {
            $originalId = $assignPosterRecord->id_poster_original;

            $isTmdb = MoviePoster::where('id', $originalId)
                ->where('id_movie', $safeId)
                ->exists();

            if ($isTmdb) {
                $activeSource = 'tmdb';
            } else {
                $imdbModelClass = 'App\\Models\\Posters' . ucfirst($typeMovieSlug);

                if (class_exists($imdbModelClass)) {
                    $isImdb = $imdbModelClass::where('id', $originalId)
                        ->where('id_movie', $safeId)
                        ->exists();

                    if ($isImdb) {
                        $activeSource = 'imdb';
                    }
                }
            }
        }
        $infoMovieData['assigned_media_source'] = $activeSource;
        if (!empty($modelArr['collection'])) {
            foreach ($modelArr['collection'] as $key => $itemCollection) {
                $collection = Collection::with('category')->find($itemCollection['collection_id'])->toArray();
                $infoMovieData['collection']['id'][$key] = $itemCollection['franchise_id']
                    ? 'fr_' . $itemCollection['collection_id'] . $itemCollection['franchise_id']
                    : $itemCollection['collection_id'];
                $infoMovieData['collection']['catInfo'][$key]['label'] = $collection[$currentLocaleLabel] ?? null;
                $infoMovieData['collection']['catInfo'][$key]['category_value'] = $collection['category'][0]['value'] ?? null;
            }
            $infoMovieData['collection']['viewed'] = (bool)($modelArr['collection'][0]['viewed'] ?? false);
            $infoMovieData['collection']['short'] = (bool)($modelArr['collection'][0]['short'] ?? false);
            $infoMovieData['collection']['adult'] = (bool)($modelArr['collection'][0]['adult'] ?? false);
            unset($infoMovieData['collection'][0]);
        }

        unset($modelArr);

        return $infoMovieData;
    }

    private function getPosterUrl(string $id, int $typeMovie, array $modelArr, string $relationPosterName): string
    {
        $currentLocale = app()->getLocale();

        $assignPoster = AssignPoster::where('id_movie', $id)->first();

        // ==========================================
        // ПРИОРИТЕТ 1: Media API
        // ==========================================
        $posterType = (!empty($assignPoster->id_poster_ru)) ? 'russian_poster' : 'original_poster';

        $hasher = new IdHasher($id);
        $hashDecodeId = $hasher->getResult();
        $apiService = new ApiRequestImages();

        $posterApi = $apiService->sendApiRequest(
            env('API_HOST_URL') . "/api/images/{$typeMovie}/{$hashDecodeId}/{$posterType}"
        );

        if (($posterApi['status'] ?? 0) === 200 && !empty($posterApi['data']['success'])) {
            $images = $posterApi['data']['images'] ?? [];
            if (!empty($images['medium'][0]['url'] ?? null)) {
                return $images['medium'][0]['url'];
            }
        }

        // ==========================================
        // ПРИОРИТЕТ 2: MoviePoster (TMDB)
        // ==========================================
        $assignField = ($currentLocale === 'ru') ? 'id_poster_ru' : 'id_poster_original';
        $assignedId = $assignPoster->$assignField ?? null;

        if ($assignedId) {
            $tmdbPoster = MoviePoster::where('id', $assignedId)
                ->where('id_movie', $id)
                ->first();

            if ($tmdbPoster) {
                return $tmdbPoster->display_url;
            }
        }

        // Фоллбек 1: любой постер TMDB для этого фильма и текущей локали
        $tmdbPosterByLocale = MoviePoster::where('id_movie', $id)
            ->where('locale', $currentLocale)
            ->orderBy('id', 'desc')
            ->first();

        if ($tmdbPosterByLocale) {
            return $tmdbPosterByLocale->display_url;
        }

        // Фоллбек 2: если локаль RU, пробуем взять оригинальный постер (тоже со строгой проверкой)
        if ($currentLocale === 'ru' && !empty($assignPoster->id_poster_original)) {
            $tmdbPosterOriginal = MoviePoster::where('id', $assignPoster->id_poster_original)
                ->where('id_movie', $id)
                ->first();

            if ($tmdbPosterOriginal) {
                return $tmdbPosterOriginal->display_url;
            }
        }

        // ==========================================
        // ПРИОРИТЕТ 3: Старый srcset (IMDb)
        // ==========================================
        // Благодаря eager load в методе show(), $modelArr[$relationPosterName] содержит
        // постеры ТОЛЬКО для этого конкретного фильма.
        if (!empty($modelArr[$relationPosterName]) && is_array($modelArr[$relationPosterName])) {
            $assignedLegacyId = $assignPoster->id_poster_original ?? null;

            // 1. Ищем точное совпадение ID в загруженных постерах этого фильма
            if ($assignedLegacyId) {
                foreach ($modelArr[$relationPosterName] as $poster) {
                    if (isset($poster['id']) && (string)$poster['id'] === (string)$assignedLegacyId) {
                        return trim($poster['srcset'] ?? '');
                    }
                }
            }

            // 2. Если назначенного постера нет или он не найден, берем первый доступный постер этого фильма
            $firstLegacyPoster = reset($modelArr[$relationPosterName]);
            if (!empty($firstLegacyPoster['srcset'])) {
                $img = explode(',', $firstLegacyPoster['srcset']);
                return trim($img[0] ?? '');
            }
        }

        return '';
    }
    public function publication(Request $request): array
    {
        try {
            $dataRequest = $request->all();
            if (empty($dataRequest)) {
                return ['success' => false];
            }

            $movieId = $dataRequest['data']['movie_id'] ?? null;

            if (!$movieId) {
                return [
                    'success' => false,
                    'status' => 200,
                    'message' => 'Movie ID is required',
                ];
            }

            $colExists = CollectionsCategoriesPivot::query()->where('id_movie', $movieId)->exists();
            if (!$colExists) {
                return [
                    'success' => false,
                    'status' => 200,
                    'message' => 'The movie is not added to any collection',
                ];
            }

            $posterExists = AssignPoster::query()
                ->where('id_movie', $movieId)
                ->whereNotNull('id_poster_original')
                ->exists();

            if (!$posterExists) {
                return [
                    'success' => false,
                    'status' => 200,
                    'message' => 'The movie is not assigned to posters table',
                ];
            }

            $this->normalizeLocalizationData($movieId);

            $updated = MovieInfo::query()
                ->where('id_movie', $movieId)
                ->update(['published' => 1]);

            if ($updated) {
                return [
                    'success' => true,
                    'status' => 200,
                    'message' => 'The movie is awaiting for export',
                ];
            }

            return [
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update movie publication status',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function update(Request $request)
    {
        $dataRequest = $request->all();
        $match = preg_match("/tt\d{1,10}/",$dataRequest['data']['id'],$matches, PREG_UNMATCHED_AS_NULL);
        $id = $match > 0 ? $dataRequest['data']['id'] : null;
        $dataForm = ($dataRequest['data']['form']) && is_array($dataRequest['data']['form']) ? $dataRequest['data']['form'] : [];
        $locale = $dataRequest['data']['lang'] === 'ru' || 'en' ? $dataRequest['data']['lang'] : '';

        if (!empty($dataForm) && $id){
            $model = modelByName('MovieInfo');
            $localizingModel = modelByName('MoviesInfo'.ucfirst($locale));
            transaction( function () use ( $dataForm, $id, $model, $localizingModel ){
                $model::where('id_movie',$id)->update([
                    'title' => $dataForm['title'],
                    'original_title' => $dataForm['original_title'],
                    'year_release' => $dataForm['year_release'],
                    'restrictions' => $dataForm['restrictions'],
                    'runtime' => $dataForm['runtime'],
                    'rating' => $dataForm['rating'],
                    'budget' => $dataForm['budget'],
                ]);
                $localizingModel::where('id_movie',$id)->update([
                    'release_date' => $dataForm['release_date'],
                    'story_line' => $dataForm['story_line']
                ]);
            });
            return ['success'=>true];
        }
        return ['success'=>false];
    }

    public function destroy(Request $request)
    {
        $dataRequest = $request->all();
        $match = preg_match("/tt\d{1,10}/",$dataRequest['id'],$matches, PREG_UNMATCHED_AS_NULL);
        $id = $match > 0 ? $dataRequest['id'] : null;
        $type = is_int(getTableSegmentOrTypeId($dataRequest['type'])) ? $dataRequest['type'] : null;

        $typesTables = [
            0=>'Images',
            1=>'Posters',
        ];
        if (!empty($id) && !empty($type)) {
            transaction( function () use ( $typesTables, $id, $type ){
                MovieInfo::query()->where('id_movie',$id)->delete();
                MoviesInfoEn::query()->where('id_movie',$id)->delete();
                MoviesInfoRu::query()->where('id_movie',$id)->delete();
                AssignPoster::query()->where('id_movie',$id)->delete();
                TagsMoviesPivot::query()->where('id_movie',$id)->delete();
                CollectionsCategoriesPivot::query()->where('id_movie',$id)->delete();
                foreach ($typesTables as $table){
                    $model = convertVariableToModelName($table,$type, ['App', 'Models']);
                    $model::where('id_movie',$id)->delete();
                }
            });
        }
    }

    private function normalizePersonData($data): array
    {
        if (!is_array($data)) {
            return [];
        }
        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && count($value) === 1) {
                $name = array_key_first($value);
                $normalized[$key] = $name;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function normalizeLocalizationData(string $movieId): void
    {
        $models = [
            MoviesInfoRu::class,
            MoviesInfoEn::class,
        ];

        foreach ($models as $modelClass) {

            $record = $modelClass::where('id_movie', $movieId)->first();

            if (!$record) {
                continue;
            }

            $changed = false;

            $directors = json_decode($record->directors, true) ?: [];
            $normalizedDirectors = $this->normalizePersonData($directors);
            if (json_encode($directors) !== json_encode($normalizedDirectors)) {
                $record->directors = json_encode($normalizedDirectors, JSON_UNESCAPED_UNICODE);
                $changed = true;
            }

            $writers = json_decode($record->writers, true) ?: [];
            $normalizedWriters = $this->normalizePersonData($writers);
            if (json_encode($writers) !== json_encode($normalizedWriters)) {
                $record->writers = json_encode($normalizedWriters, JSON_UNESCAPED_UNICODE);
                $changed = true;
            }

            if ($changed) {
                $record->save();
            }
        }
    }
    }
