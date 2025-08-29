<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CollectionsCategoriesPivot;
use App\Models\MovieInfo;
use App\Services\ApiRequestImages;
use App\Services\IdHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;

class SectionsController extends Controller
{
    public function index(Request $request, $slug): array
    {
        $hashedIds = [];
        $apiPreviewLinks = [];
        $allowedSectionsNames = [
            0 => 'yellow',
            1 => 'green',
            2 => 'blue',
            3 => 'cyan',
            4 => 'purple',
            5 => 'black',
            6 => 'brown',
            7 => 'red',
            8 => 'silver',
            9 => 'tan',
        ];
        $currentLocale = Lang::locale();
        if (in_array($slug, $allowedSectionsNames)) {
            $section = Category::where('value', $slug)->with('children')->first()->toArray();
            foreach ($section['children'] as $collection) {
                $collectionIds[] = $collection['id'];
            }
            $pivot = CollectionsCategoriesPivot::query();
            $moviesIds = $pivot->whereIn('collection_id', $collectionIds)->get(['id_movie', 'type_film'])->toArray();

            $typeFilmArray = [];
            $collection = collect();
            $collectionResponse = [];

            if (!empty($moviesIds)) {
                array_walk($moviesIds, function ($item, $key) use (&$typeFilmArray) {
                    $typeFilmArray[] = $item['id_movie'];
                });
                $model = modelByName('MovieInfo');
                $allowedFilterFields = $model->getFillable();
                $titleFieldName = transformTitleByLocale();

                if ($query = $request->query('search')) {
                    $searchQuery = trim(strtolower(strip_tags($query)));
                    $model = $model->whereIn('id_movie', $typeFilmArray)->where($allowedFilterFields[1], 'like', '%' . $searchQuery . '%')->orWhere($allowedFilterFields[3], 'like', '%' . $searchQuery . '%');
                }

                $collection->add($model->select('type_film', 'id_movie', $titleFieldName, 'published', 'year_release', 'created_at', 'updated_at')->whereIn('id_movie', $typeFilmArray)->with(['assignPoster', 'categories'])->get()->all());
                $collapsed = $collection->collapse();
                $sorted = $collapsed->sort();
                if ($sorted->isNotEmpty()) {
                    $allowedSortFields = ['desc', 'asc'];

                    $limit = $request->query('limit', 50);
                    $sortDir = strtolower($request->query('spin', 'asc'));
                    $sortBy = $request->query('orderBy', 'updated_at');
                    $perPage = $request->query('page', 1);
                    if (!empty($searchQuery)) {
                        $perPage = 1;
                    }
                    if (!in_array($sortBy, $allowedFilterFields)) {
                        $sortBy = $allowedSortFields[0];
                    }

                    if (in_array($sortDir, $allowedSortFields)) {
                        if ($sortDir == 'desc') {
                            $sorted = $sorted->sortByDesc($sortBy);
                        } elseif ($sortDir == 'asc') {
                            $sorted = $sorted->sortBy($sortBy);
                        }
                    }
                    $collectionSort = $sorted->forPage($perPage, $limit);
                    $collectionSortArr = $collectionSort->values()->toArray();
                    foreach ($collectionSortArr as $movieItem) {
                        if ($movieItem['assign_poster']) {
                            $idsPostersArr[getTableSegmentOrTypeId($movieItem['assign_poster']['type_film'])][] = $movieItem['assign_poster']['id_poster_original'];
                        }
                    }
                    if (!empty($idsPostersArr)) {
                        $posterCollection = collect();
                        foreach ($idsPostersArr as $key => $item) {
                            $model = convertVariableToModelName('Posters', $key, ['App', 'Models']);
                            $posterCollection->add($model->select('srcset', 'id_movie')->whereIn('id', $item)->get()->all());
                        }
                        $collapsedPosters = $posterCollection->collapse()->toArray();
                    }
                    foreach ($collectionSortArr as $movieItem) {
                        $hasher = new IdHasher($movieItem['id_movie']);
                        $hashedIds[$hasher->getResult() ?? ''][] = ['old_id' => $movieItem['id_movie']];
                        $hashedIds['api'][] = $hasher->getResult();
                    }

                    if (!empty($hashedIds)) {
                        $data = ['movieIds' => $hashedIds['api']];
                        $apiService = new ApiRequestImages();
                        $previewImagesApi = $apiService->sendApiRequest(env('API_HOST_URL') . "/api/images/batch/types/original_poster/small", 'POST', $data, true);
                        if ($previewImagesApi['status'] === 200 && $previewImagesApi['data']['success']) {
                            unset($hashedIds['api']);
                            foreach ($previewImagesApi['data']['images'] as $k => $image) {
                                if (is_array($image) && !empty($image) && isset($image[0]['url']) && is_string($image[0]['url']) && filter_var($image[0]['url'], FILTER_VALIDATE_URL)) {
                                    $imageUrl = str_replace('http://media-api.local:8081', env('API_HOST_URL'), $image[0]['url']);
                                    $apiPreviewLinks[$k] = $imageUrl;
                                }
                            }
                        }
                    }
                    foreach ($collectionSort->values()->toArray() as $k => $item) {
                        if (!empty($section)){
                            foreach ($section['children'] as $col){
                                foreach ($item['categories'] as $key => $cat){
                                    if (!empty($cat['collection_id'] )){
                                        if ($cat['collection_id'] == $col['id'] ){
                                            $collectionResponse['data'][$k]['collection'][$key]['label'] = $col['label_'.$currentLocale];
                                            $collectionResponse['data'][$k]['collection'][$key]['value'] = $col['value'];
                                        }
                                    }
                                }
                            }
                        }
                        if (!empty($collapsedPosters)){
                            foreach ($hashedIds as $hashKey => $ids) {
                                foreach ($collapsedPosters as $posterItem){
                                    if ($item['id_movie'] == $posterItem['id_movie']){
                                        $img = explode(',',$posterItem['srcset'] ?? '');
                                        if ($posterItem['id_movie'] == $ids[0]['old_id']){
                                            $collectionResponse['data'][$k]['poster'] = (!empty($previewImagesApi['data']['images'][$hashKey])) ? $previewImagesApi['data']['images'][$hashKey][0]['url'] : $img[0] ?? '';
                                        }
                                    }
                                }
                            }
                        }
                        $collectionResponse['data'][$k]['title'] = $item['title']??$item['original_title'];
                        $collectionResponse['data'][$k]['published'] = statusSelection($item['published']) ?? [];
                        $collectionResponse['data'][$k]['created_at'] = date('Y-m-d', strtotime($item['created_at'])) ?? '';
                        $collectionResponse['data'][$k]['updated_at'] = date('Y-m-d', strtotime($item['updated_at'])) ?? '';
                        $collectionResponse['data'][$k]['year'] = $item['year_release'] ?? null;
                        $collectionResponse['data'][$k]['id_movie'] = $item['id_movie'] ?? '';
                        $collectionResponse['data'][$k]['type_film'] = getTableSegmentOrTypeId($item['type_film']) ?? '';
                    }
                    unset($hashedIds);
                    unset($previewImagesApi);
                    foreach ($section['children'] as $k => $item){
                        $collectionResponse['collections'][$k]['label'] = $item['label_'.$currentLocale];
                        $collectionResponse['collections'][$k]['value'] = $item['value'];
                    }

                }
                $collectionResponse['total'] = $collapsed->count();
                $collectionResponse['locale'] = LanguageController::localizingSectionsList();
                $collectionResponse['title'] = $section['title_'.$currentLocale];
                return $collectionResponse;
            }
        }
        return [];
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
    public function updateSection (Request $request) : bool
    {
        $data = Validator::make($request->all(),[
            'id' => 'required|int',
            'index_id' => '|string|max:10',
            'title_en' => 'required|string|max:30',
            'title_ru' => 'required|string|max:30',
        ])->safe()->all();
        if (!empty($data['id'])){
            Category::where('id',$data['id'])->update([
                'index_id' => $data['index_id'],
                'title_en' => $data['title_en'],
                'title_ru' => $data['title_ru']
            ]);
            return true;
        }
        return false;
    }

    public function updateImages(Request $request)
    {
        Log::info('Request input:', ['input' => $request->all()]);
        Log::info('Request files:', ['files' => $request->file()]);

        $request->validate([
            'sections.*.section_logo' => 'required|image|mimes:jpeg,png,gif|max:10240',
            'sections.*.backdrop' => 'required|image|mimes:jpeg,png,gif|max:10240',
            'sections.*.film_logo_ru' => 'nullable|image|mimes:jpeg,png,gif|max:10240',
            'sections.*.film_logo_en' => 'nullable|image|mimes:jpeg,png,gif|max:10240',
            'sections.*.poster_ids' => 'nullable|array|min:7|max:10',
            'sections.*.poster_ids.*' => 'string|regex:/^tt\d{7,}$/',
        ]);

        $sectionsFiles = $request->file('sections') ?? [];
        $sectionsInput = $request->input('sections', []);

        if (empty($sectionsFiles)) {
            Log::error('No sections files received');
            return response()->json(['success' => false, 'message' => 'Нет данных для обработки (файлы отсутствуют).'], 422);
        }

        foreach ($sectionsFiles as $sectionId => $data) {
            if (!isset($data['section_logo']) || !isset($data['backdrop'])) {
                Log::error("Missing required files for section {$sectionId}");
                return response()->json(['success' => false, 'message' => "Отсутствуют обязательные файлы для секции {$sectionId}"], 422);
            }

            // Сохранение section_logo
            $sectionLogoPath = "sections/{$sectionId}/section_logo";
            if (Storage::disk('public')->exists($sectionLogoPath)) {
                Storage::disk('public')->deleteDirectory($sectionLogoPath);
            }
            $sectionLogoFile = $data['section_logo'];
            $sectionLogoFilename = $sectionLogoFile->getClientOriginalName();
            $savedPath = $sectionLogoFile->storeAs($sectionLogoPath, $sectionLogoFilename, 'public');
            Log::info("Saved section_logo for section {$sectionId}: {$savedPath}");

            // Сохранение backdrop
            $backdropPath = "sections/{$sectionId}/backdrop";
            if (Storage::disk('public')->exists($backdropPath)) {
                Storage::disk('public')->deleteDirectory($backdropPath);
            }
            $backdropFile = $data['backdrop'];
            $backdropFilename = $backdropFile->getClientOriginalName();
            $savedPath = $backdropFile->storeAs($backdropPath, $backdropFilename, 'public');
            Log::info("Saved backdrop for section {$sectionId}: {$savedPath}");

            // Сохранение film_logo_ru
            if (isset($data['film_logo_ru'])) {
                $filmLogoRuPath = "sections/{$sectionId}/film_logos/ru";
                if (Storage::disk('public')->exists($filmLogoRuPath)) {
                    Storage::disk('public')->deleteDirectory($filmLogoRuPath);
                }
                $filmLogoRuFile = $data['film_logo_ru'];
                $filmLogoRuFilename = $filmLogoRuFile->getClientOriginalName();
                $savedPath = $filmLogoRuFile->storeAs($filmLogoRuPath, $filmLogoRuFilename, 'public');
                Log::info("Saved film_logo_ru for section {$sectionId}: {$savedPath}");
            }

            // Сохранение film_logo_en
            if (isset($data['film_logo_en'])) {
                $filmLogoEnPath = "sections/{$sectionId}/film_logos/en";
                if (Storage::disk('public')->exists($filmLogoEnPath)) {
                    Storage::disk('public')->deleteDirectory($filmLogoEnPath);
                }
                $filmLogoEnFile = $data['film_logo_en'];
                $filmLogoEnFilename = $filmLogoEnFile->getClientOriginalName();
                $savedPath = $filmLogoEnFile->storeAs($filmLogoEnPath, $filmLogoEnFilename, 'public');
                Log::info("Saved film_logo_en for section {$sectionId}: {$savedPath}");
            }

            // Обработка poster_ids
            $posterIds = $sectionsInput[$sectionId]['poster_ids'] ?? [];
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
                    $apiData = ['movieIds' => array_keys($hashedIds)];
                    $apiService = new ApiRequestImages();
                    $previewImagesApi = $apiService->sendApiRequest(env('API_HOST_URL') . "/api/images/batch/types/original_poster/small", 'POST', $apiData, true);

                    if ($previewImagesApi['status'] === 200 && $previewImagesApi['data']['success']) {
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
                                Log::info("Saved poster for section {$sectionId}: {$path}");
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
                Log::info("Files in {$path}:", ['files' => $files]);
                foreach ($files as $file) {
                    $url = Storage::disk('public')->url($file);
                    $result[$sectionId][$key][] = $url;
                }
            }
        }
        Log::info("Section images response:", ['result' => $result]);
        return response()->json($result);
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
