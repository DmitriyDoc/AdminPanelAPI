<?php

namespace App\Http\Controllers;

use App\Models\AssignPoster;
use App\Models\Category;
use App\Models\CollectionsCategoriesPivot;
use App\Models\MovieCategory;
use App\Models\MovieInfo;
use App\Models\TMDb\MoviePoster;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;

class SectionsController extends Controller
{
    public function index(Request $request, string $slug): array
    {
        $allowedSectionsNames = ['yellow', 'green', 'blue', 'cyan', 'purple', 'black', 'brown', 'red', 'silver', 'tan'];

        if (!in_array($slug, $allowedSectionsNames)) {
            return [];
        }

        $currentLocale = App::getLocale();

        $section = Category::where('value', $slug)->with('children')->first();
        if (!$section) {
            return [];
        }

        $collectionIds = $section->children->pluck('id')->toArray();
        if (empty($collectionIds)) {
            return $this->buildEmptyResponse($section, $currentLocale);
        }

        $movieIds = CollectionsCategoriesPivot::whereIn('collection_id', $collectionIds)
            ->pluck('id_movie')
            ->unique()
            ->toArray();

        if (empty($movieIds)) {
            return $this->buildEmptyResponse($section, $currentLocale);
        }

        $model = modelByName('MovieInfo');
        $titleFieldName = transformTitleByLocale();
        $allowedFilterFields = $model->getFillable();

        $query = $model->select('id_movie', 'type_film', $titleFieldName, 'published', 'year_release', 'created_at', 'updated_at')
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
            return $this->buildEmptyResponse($section, $currentLocale, 0);
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

        $responseData = [];
        $sectionChildrenMap = collect($section->children)->keyBy('id');

        foreach ($movieItems as $item) {
            $posterUrl = $this->getPosterUrlForSection(
                $item->id_movie,
                $item,
                $assignField,
                $assignedPostersMap,
                $localePosters,
                $legacyPostersMap
            );

            $categoriesData = [];
            foreach ($item->categories as $cat) {
                if (isset($sectionChildrenMap[$cat->collection_id])) {
                    $child = $sectionChildrenMap[$cat->collection_id];
                    $categoriesData[] = [
                        'label' => $child->{'label_' . $currentLocale},
                        'value' => $child->value,
                    ];
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
                'collection'    => $categoriesData,
            ];
        }

        $collectionsList = [];
        foreach ($section->children as $child) {
            $collectionsList[] = [
                'label' => $child->{'label_' . $currentLocale},
                'value' => $child->value,
            ];
        }

        return [
            'data'        => $responseData,
            'total'       => $paginatedMovies->total(),
            'locale'      => LanguageController::localizingSectionsList(),
            'title'       => $section->{'title_' . $currentLocale},
            'collections' => $collectionsList,
        ];
    }
    private function getPosterUrlForSection(string $idMovie, object $item, string $assignField, array $assignedPostersMap, $localePosters, array $legacyPostersMap): string
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
    private function buildEmptyResponse(object $section, string $currentLocale, int $total = 0): array
    {
        $collectionsList = [];
        foreach ($section->children as $child) {
            $collectionsList[] = [
                'label' => $child->{'label_' . $currentLocale},
                'value' => $child->value,
            ];
        }

        return [
            'data'        => [],
            'total'       => $total,
            'locale'      => LanguageController::localizingSectionsList(),
            'title'       => $section->{'title_' . $currentLocale},
            'collections' => $collectionsList,
        ];
    }
    private function getFilesFromSrcBatch(array $urls, array $ids, string $sectionId): array
    {
        if (count($urls) !== count($ids)) {
            Log::error("MISMATCH-URLS-IDS--SECTION--{$sectionId} ---> URL-COUNT: " . count($urls) . ", ID-COUNT: " . count($ids));
            return [];
        }

        $client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'allow_redirects' => true,
        ]);

        $requests = function () use ($urls,$sectionId) {
            foreach ($urls as $index => $url) {
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    Log::debug("BATCH-REQUEST--SECTION--{$sectionId} ---> INDEX: {$index}, URL: {$url}");
                    yield new \GuzzleHttp\Psr7\Request('GET', $url, [
                        'User-Agent' => 'Mozilla/5.0 (compatible; YourApp/1.0)',
                        'Authorization' => 'Bearer ' . env('API_TOKEN'),
                    ]);
                } else {
                    Log::warning("BATCH-SKIP-INVALID-URL--SECTION--{$sectionId} ---> INDEX: {$index}, URL: {$url}");
                }
            }
        };

        $results = [];
        $pool = new Pool($client, $requests(), [
            'concurrency' => 10,
            'fulfilled' => function ($response, $index) use (&$results, $urls, $ids, $sectionId) {
                if ($response->getStatusCode() === 200) {
                    $content = $response->getBody()->getContents();
                    $expectedSize = $response->hasHeader('Content-Length') ? (int) $response->getHeaderLine('Content-Length') : null;
                    $finfo = finfo_open();
                    $mimeType = finfo_buffer($finfo, $content, FILEINFO_MIME_TYPE);
                    finfo_close($finfo);
                    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
                        Log::warning("INVALID-MIME-TYPE--SECTION--{$sectionId} ---> URL: {$urls[$index]}, MIME: {$mimeType}");
                        $results[$ids[$index]] = ['content' => null, 'expectedSize' => null, 'lastUrl' => $urls[$index]];
                    } else {
                        $results[$ids[$index]] = [
                            'content' => $content,
                            'expectedSize' => $expectedSize,
                            'lastUrl' => $urls[$index],
                        ];
                    }
                } else {
                    $results[$ids[$index]] = ['content' => null, 'expectedSize' => null, 'lastUrl' => $urls[$index]];
                }
            },
            'rejected' => function ($reason, $index) use (&$results, $urls, $ids, $sectionId) {
                Log::info("BATCH-EXCEPTION--SECTION--{$sectionId} ---> INDEX: {$index}, URL: {$urls[$index]}, ERROR: {$reason->getMessage()}");
                $results[$ids[$index]] = ['content' => null, 'expectedSize' => null, 'lastUrl' => $urls[$index]];
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $results;
    }
    public function updateSection (Request $request) : string
    {
        $data = Validator::make($request->all(),[
            'id' => 'required|int',
            'index_id' => 'required|string|max:10',
            'title_en' => 'required|string|max:30',
            'title_ru' => 'required|string|max:30',
        ])->safe()->all();

        $category = Category::findOrFail($data['id']);
        $category->update($data);

        $typeFilm = $category->movieInfo?->type_film;

        return in_array($typeFilm, [2, 5]) ? 'tv' : 'movie';
    }
    public function updateImages(Request $request)
    {
        $request->validate([
            // Делаем поля nullable - могут быть файлы ИЛИ URL
            'sections.*.section_logo' => 'nullable|image|mimes:jpeg,png,gif|max:10240',
            'sections.*.backdrop' => 'nullable|image|mimes:jpeg,png,gif|max:10240',
            'sections.*.film_logo_ru' => 'nullable|image|mimes:jpeg,png,gif|max:10240',
            'sections.*.film_logo_en' => 'nullable|image|mimes:jpeg,png,gif|max:10240',

            // Отдельные поля для URL из TMDB
            'sections.*.section_logo_url' => 'nullable|url',
            'sections.*.backdrop_url' => 'nullable|url',
            'sections.*.film_logo_ru_url' => 'nullable|url',
            'sections.*.film_logo_en_url' => 'nullable|url',

            'sections.*.poster_ids' => 'nullable|array|min:7|max:10',
            'sections.*.poster_ids.*' => 'string|regex:/^tt\d{7,}$/',
        ]);

        $sectionsFiles = $request->file('sections') ?? [];
        $sectionsInput = $request->input('sections', []);

        // Проверяем что есть хоть какие-то данные
        $hasAnyData = false;
        foreach ($sectionsInput as $sectionId => $data) {
            if (!empty($sectionsFiles[$sectionId]) ||
                !empty($data['section_logo_url']) ||
                !empty($data['backdrop_url']) ||
                !empty($data['film_logo_ru_url']) ||
                !empty($data['film_logo_en_url']) ||
                !empty($data['poster_ids'])) {
                $hasAnyData = true;
                break;
            }
        }

        if (!$hasAnyData && empty($sectionsFiles)) {
            Log::error('No sections data received');
            return response()->json(['success' => false, 'message' => 'Нет данных для обработки.'], 422);
        }

        foreach ($sectionsInput as $sectionId => $data) {
            // === Section Logo ===
            $sectionLogoPath = "sections/{$sectionId}/section_logo";
            if (Storage::disk('public')->exists($sectionLogoPath)) {
                Storage::disk('public')->deleteDirectory($sectionLogoPath);
            }

            if (isset($sectionsFiles[$sectionId]['section_logo'])) {
                // Загружен файл вручную
                $sectionLogoFile = $sectionsFiles[$sectionId]['section_logo'];
                $sectionLogoFilename = $sectionLogoFile->getClientOriginalName();
                $sectionLogoFile->storeAs($sectionLogoPath, $sectionLogoFilename, 'public');
            } elseif (!empty($data['section_logo_url'])) {
                // URL из TMDB
                $this->downloadAndSaveImage($data['section_logo_url'], "{$sectionLogoPath}/logo.png");
            }

            // === Backdrop ===
            $backdropPath = "sections/{$sectionId}/backdrop";
            if (Storage::disk('public')->exists($backdropPath)) {
                Storage::disk('public')->deleteDirectory($backdropPath);
            }

            if (isset($sectionsFiles[$sectionId]['backdrop'])) {
                // Загружен файл вручную
                $backdropFile = $sectionsFiles[$sectionId]['backdrop'];
                $backdropFilename = $backdropFile->getClientOriginalName();
                $backdropFile->storeAs($backdropPath, $backdropFilename, 'public');
            } elseif (!empty($data['backdrop_url'])) {
                // URL из TMDB
                $this->downloadAndSaveImage($data['backdrop_url'], "{$backdropPath}/backdrop.jpg");
            }

            // === Film Logo RU ===
            $filmLogoRuPath = "sections/{$sectionId}/film_logos/ru";
            if (Storage::disk('public')->exists($filmLogoRuPath)) {
                Storage::disk('public')->deleteDirectory($filmLogoRuPath);
            }

            if (isset($sectionsFiles[$sectionId]['film_logo_ru'])) {
                // Загружен файл вручную
                $filmLogoRuFile = $sectionsFiles[$sectionId]['film_logo_ru'];
                $filmLogoRuFilename = $filmLogoRuFile->getClientOriginalName();
                $filmLogoRuFile->storeAs($filmLogoRuPath, $filmLogoRuFilename, 'public');
            } elseif (!empty($data['film_logo_ru_url'])) {
                // URL из TMDB
                $this->downloadAndSaveImage($data['film_logo_ru_url'], "{$filmLogoRuPath}/logo.png");
            }

            // === Film Logo EN ===
            $filmLogoEnPath = "sections/{$sectionId}/film_logos/en";
            if (Storage::disk('public')->exists($filmLogoEnPath)) {
                Storage::disk('public')->deleteDirectory($filmLogoEnPath);
            }

            if (isset($sectionsFiles[$sectionId]['film_logo_en'])) {
                // Загружен файл вручную
                $filmLogoEnFile = $sectionsFiles[$sectionId]['film_logo_en'];
                $filmLogoEnFilename = $filmLogoEnFile->getClientOriginalName();
                $filmLogoEnFile->storeAs($filmLogoEnPath, $filmLogoEnFilename, 'public');
            } elseif (!empty($data['film_logo_en_url'])) {
                // URL из TMDB
                $this->downloadAndSaveImage($data['film_logo_en_url'], "{$filmLogoEnPath}/logo.png");
            }

            // === Posters (без изменений) ===
            $posterIds = $data['poster_ids'] ?? [];
            if (!empty($posterIds)) {
                $validIds = [];
                foreach ($posterIds as $imdbId) {
                    if (MovieInfo::where('id_movie', $imdbId)->where('published', 2)->exists()) {
                        $validIds[] = $imdbId;
                    } else {
                        Log::warning("Invalid or unpublished IMDb ID: {$imdbId} for section {$sectionId}");
                    }
                }

                if (count($validIds) < 7 || count($validIds) > 10) {
                    Log::error("Invalid number of poster IDs for section {$sectionId}: " . count($validIds));
                    return response()->json(['success' => false, 'message' => "Недостаточно валидных ID для секции {$sectionId} (нужно 7-10, если указаны)."], 422);
                }

                $movieItems = MovieInfo::whereIn('id_movie', $validIds)->where('published', 2)->get(['id', 'id_movie'])->toArray();
                $hashedIds = [];
                foreach ($movieItems as $movieItem) {
                    $hasher = new IdHasher($movieItem['id_movie']);
                    $hashedId = $hasher->getResult();
                    if ($hashedId) {
                        $hashedIds[$hashedId] = ['old_id' => $movieItem['id']];
                    } else {
                        Log::warning("Failed to hash ID for movie {$movieItem['id']} in section {$sectionId}");
                    }
                }

                $apiPreviewLinks = [];
                if (!empty($hashedIds)) {
                    $movieTypesMap = [];
                    $movieItems = MovieInfo::whereIn('id_movie', $validIds)->where('published', 2)->get(['id_movie', 'type_film'])->toArray();
                    foreach ($movieItems as $movieItem) {
                        $hasher = new IdHasher($movieItem['id_movie']);
                        $hashedId = $hasher->getResult();
                        if ($hashedId) {
                            $movieTypesMap[$hashedId] = $movieItem['type_film'];
                        }
                    }

                    $apiData = [
                        'movieIds' => array_keys($hashedIds),
                        'movieTypes' => $movieTypesMap,
                    ];
                    $apiService = new ApiRequestImages();
                    $previewImagesApi = $apiService->sendApiRequest(env('API_HOST_URL') . "/api/images/batch/types/original_poster/small", 'POST', $apiData, true);

                    if ($previewImagesApi && isset($previewImagesApi['status']) && $previewImagesApi['status'] === 200 && isset($previewImagesApi['data']['success']) && $previewImagesApi['data']['success']) {
                        foreach ($previewImagesApi['data']['images'] as $k => $image) {
                            if (is_array($image) && !empty($image) && isset($image[0]['url']) && is_string($image[0]['url']) && filter_var($image[0]['url'], FILTER_VALIDATE_URL)) {
                                $imageUrl = str_replace('http://media-api.local:8081', env('API_HOST_URL'), $image[0]['url']);
                                $apiPreviewLinks[$k] = $imageUrl;
                            }
                        }
                    } else {
                        Log::error("API request failed for section {$sectionId}", ['response' => $previewImagesApi]);
                    }
                }

                if (!empty($apiPreviewLinks)) {
                    $urls = array_values($apiPreviewLinks);
                    $ids = array_keys($apiPreviewLinks);
                    $files = $this->getFilesFromSrcBatch($urls, $ids, $sectionId);
                    $posterPath = "sections/{$sectionId}/posters";
                    if (Storage::disk('public')->exists($posterPath)) {
                        Storage::disk('public')->deleteDirectory($posterPath);
                    }

                    foreach ($apiPreviewLinks as $hashedId => $imageUrl) {
                        $oldId = $hashedIds[$hashedId]['old_id'] ?? null;
                        $file = $files[$hashedId] ?? null;
                        if ($oldId && $file && !empty($file['content'])) {
                            try {
                                $finfo = finfo_open();
                                $mimeType = finfo_buffer($finfo, $file['content'], FILEINFO_MIME_TYPE);
                                finfo_close($finfo);
                                if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
                                    Log::warning("Invalid MIME type for poster in section {$sectionId}: {$mimeType}, URL: {$imageUrl}");
                                    continue;
                                }

                                $extension = $mimeType === 'image/png' ? 'png' : 'jpg';
                                $filename = "poster_{$hashedId}.{$extension}";
                                $path = "sections/{$sectionId}/posters/{$filename}";
                                Storage::disk('public')->put($path, $file['content']);
                            } catch (\Exception $e) {
                                Log::error("Error saving poster for section {$sectionId}: {$imageUrl}, Error: {$e->getMessage()}");
                            }
                        } else {
                            Log::warning("No content or invalid oldId for section {$sectionId}:", ['hashedId' => $hashedId, 'imageUrl' => $imageUrl, 'oldId' => $oldId, 'file' => $file]);
                        }
                    }
                }
            }
        }

        return response()->json(['success' => true]);
    }
    public function deleteImage(Request $request)
    {
        $data = $request->validate([
            'sectionId' => 'required|integer',
            'field' => 'required|in:section_logo,backdrop,film_logo_ru,film_logo_en,posters',
            'file' => 'required|string',
        ]);

        $path = "sections/{$data['sectionId']}/{$data['field']}/{$data['file']}";
        if ($data['field'] === 'posters') {
            $path = "sections/{$data['sectionId']}/posters/{$data['file']}";
        } elseif ($data['field'] === 'film_logo_ru') {
            $path = "sections/{$data['sectionId']}/film_logos/ru/{$data['file']}";
        } elseif ($data['field'] === 'film_logo_en') {
            $path = "sections/{$data['sectionId']}/film_logos/en/{$data['file']}";
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            Log::info("Deleted image: {$path}");
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Файл не найден'], 404);
    }
    public function sectionImages()
    {
        $sectionsDir = 'sections';
        $result = [];
        $sectionDirs = Storage::disk('public')->directories($sectionsDir);

        foreach ($sectionDirs as $sectionDir) {
            $sectionId = basename($sectionDir);
            $result[$sectionId] = [
                'section_logo' => [],
                'backdrop' => [],
                'film_logo_ru' => [],
                'film_logo_en' => [],
                'posters' => [],
            ];

            $subDirs = [
                'section_logo' => "sections/{$sectionId}/section_logo",
                'backdrop' => "sections/{$sectionId}/backdrop",
                'film_logo_ru' => "sections/{$sectionId}/film_logos/ru",
                'film_logo_en' => "sections/{$sectionId}/film_logos/en",
                'posters' => "sections/{$sectionId}/posters",
            ];

            foreach ($subDirs as $key => $path) {
                $files = Storage::disk('public')->files($path);
                foreach ($files as $file) {
                    $url = Storage::disk('public')->url($file);

                    // ← ДОБАВИТЬ: Добавляем версионирование через время изменения файла
                    $fullPath = Storage::disk('public')->path($file);
                    if (file_exists($fullPath)) {
                        $version = filemtime($fullPath);
                        $url .= (str_contains($url, '?') ? '&' : '?') . "v={$version}";
                    }

                    $result[$sectionId][$key][] = $url;
                }
            }
        }
        return response()->json($result);
    }
    private function downloadAndSaveImage(string $url, string $path): bool
    {
        try {
            $proxy = env('HTTP_PROXY') ?: env('HTTPS_PROXY') ?: null;

            $clientOptions = [
                'timeout' => 60,
                'connect_timeout' => 10,
                'allow_redirects' => true,
                'verify' => false,
            ];

            // Если прокси указан - используем его
            if ($proxy) {
                $clientOptions['proxy'] = $proxy;
            }

            $client = new Client($clientOptions);

            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error("Download failed: Status {$response->getStatusCode()}", ['url' => $url]);
                return false;
            }

            $content = $response->getBody()->getContents();

            if (empty($content)) {
                Log::error("Downloaded content is empty", ['url' => $url]);
                return false;
            }

            Storage::disk('public')->put($path, $content);

            Log::info("Successfully downloaded", [
                'url' => $url,
                'path' => $path,
                'size' => strlen($content)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Exception during download", [
                'url' => $url,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    private function doDownload(string $url, string $path): bool
    {
        try {
            $client = new Client([
                'timeout' => 60, // Увеличили до 60 секунд
                'connect_timeout' => 10,
                'allow_redirects' => true,
                'verify' => false,
            ]);

            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error("Download failed: Status {$response->getStatusCode()}", ['url' => $url]);
                return false;
            }

            $content = $response->getBody()->getContents();

            if (empty($content)) {
                Log::error("Downloaded content is empty", ['url' => $url]);
                return false;
            }

            // Проверяем, что это действительно изображение
            $finfo = finfo_open();
            $mimeType = finfo_buffer($finfo, $content, FILEINFO_MIME_TYPE);
            finfo_close($finfo);

            if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
                Log::error("Invalid mime type", ['url' => $url, 'mime' => $mimeType]);
                return false;
            }

            Storage::disk('public')->put($path, $content);

            Log::info("Successfully downloaded", [
                'url' => $url,
                'path' => $path,
                'size' => strlen($content),
                'mime' => $mimeType
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Exception during download", [
                'url' => $url,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    public function getSuggestedMovies(Request $request)
    {
        $request->validate([
            'section_id' => 'required|integer|exists:categories,id',
        ]);

        $sectionId = $request->input('section_id');
        $limit = 20;

        try {
            // Получаем секцию с её детьми (коллекциями)
            $section = Category::with('children')->findOrFail($sectionId);

            // Собираем ID всех категорий секции (родитель + дети)
            $categoryIds = [$section->id];
            if ($section->children && $section->children->count() > 0) {
                $categoryIds = array_merge($categoryIds, $section->children->pluck('id')->toArray());
            }

            // Получаем все IMDB ID фильмов из этих категорий через movie_categories
            $movieIds = MovieCategory::whereIn('id_category', $categoryIds)
                ->pluck('id_movie')
                ->unique()
                ->toArray();

            if (empty($movieIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'current_imdb_id' => $section->index_id,
                    'section_title' => $section->{'title_' . app()->getLocale()}
                ]);
            }

            // Запрос к MovieInfo
            $query = MovieInfo::whereIn('id_movie', $movieIds)
                ->where('published', 2); // Только опубликованные

            // Исключаем текущий index_id секции
            if ($section->index_id) {
                $query->where('id_movie', '!=', $section->index_id);
            }

            // Определяем поле заголовка по локали
            $titleFieldName = transformTitleByLocale();

            // Получаем фильмы с постерами, сортируем по году DESC, рейтингу DESC
            $movies = $query
                ->select('id', 'id_movie', $titleFieldName . ' as title', 'year_release', 'rating', 'type_film')
                ->with(['assignPoster'])
                ->orderBy('year_release', 'desc')
                ->orderBy('rating', 'desc')
                ->limit($limit)
                ->get();

            // Формируем ответ
            $result = [];
            $apiService = new ApiRequestImages();

            foreach ($movies as $movie) {
                $posterUrl = '';

                // Пытаемся получить постер через API (как в show методе)
                if ($movie->type_film && $movie->id_movie) {
                    try {
                        $hasher = new IdHasher($movie->id_movie);
                        $hashDecodeId = $hasher->getResult();

                        if ($hashDecodeId) {
                            // Пробуем получить через API
                            $posterApi = $apiService->sendApiRequest(
                                env('API_HOST_URL') . "/api/images/{$movie->type_film}/{$hashDecodeId}/original_poster"
                            );

                            if (isset($posterApi['status']) && $posterApi['status'] === 200 &&
                                isset($posterApi['data']['success']) && $posterApi['data']['success']) {
                                $posterUrl = $posterApi['data']['images']['medium'][0]['url'] ?? '';
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to get poster from API', [
                            'id_movie' => $movie->id_movie,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Fallback: если API не вернул постер - берём из assignPoster
                if (empty($posterUrl) && $movie->assignPoster && !empty($movie->assignPoster->srcset)) {
                    $img = explode(',', $movie->assignPoster->srcset)[0];
                    $posterUrl = trimAfterImageExtension(trim($img));
                }

                $result[] = [
                    'id' => $movie->id,
                    'id_movie' => $movie->id_movie,
                    'title' => $movie->title,
                    'year' => $movie->year_release,
                    'rating' => $movie->rating,
                    'poster' => $posterUrl,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'current_imdb_id' => $section->index_id,
                'section_title' => $section->{'title_' . app()->getLocale()},
                'total_found' => count($result)
            ]);

        } catch (\Exception $e) {
            Log::error('getSuggestedMovies error', [
                'section_id' => $sectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения списка фильмов: ' . $e->getMessage()
            ], 500);
        }
    }
    public function destroy(Request $request): void
    {
        $data = Validator::make($request->all(), [
            'id_movie' => 'required|string|max:10',
        ])->safe()->all();
        if (!empty($data)) {
            CollectionsCategoriesPivot::where('id_movie', $data['id_movie'])->delete();
        }
    }
}
