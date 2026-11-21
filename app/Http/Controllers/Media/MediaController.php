<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\TMDb\MoviePoster;
use App\Models\TMDb\MovieBackdrop;
use App\Models\AssignPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaController extends Controller
{
    private const ALLOWED_FILM_TYPES = [
        'FeatureFilm', 'MiniSeries', 'ShortFilm', 'TvMovie',
        'TvSeries', 'TvShort', 'TvSpecial', 'Video',
    ];

    private const ALLOWED_POSTER_CATEGORIES = [
        'id_poster_original', 'id_poster_ru', 'id_posters_characters',
        'id_posters_alternative', 'id_wallpaper',
    ];

    private const PREVIEW_SIZE = 'w200';      // Для миниатюр в таблицах и галереях
    private const MEDIUM_SIZE = 'w300';       // Для карточек (главное фото актера/фильма)
    private const LARGE_SIZE = 'w1280';       // Для детального просмотра (если понадобится)
    private const ORIGINAL_SIZE = 'original'; // Полное качество для поля "Ссылка"

    // Метод для получения базового URL из .env с надежным fallback
    private function getBaseUrl(): string
    {
        return env('TMDB_IMAGE_BASE_URL').'/t/p';
    }

    private function getImdbModelClass(string $type, string $slug): ?string
    {
        $candidates = [];

        if ($type === 'images') {
            $candidates[] = 'App\\Models\\Images' . $slug;
            $candidates[] = 'App\\Models\\ImagesMovies';
        } else {
            $candidates[] = 'App\\Models\\Posters' . $slug;
        }

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    private function parseWidthFromSrcset(?string $srcset): int
    {
        if (!$srcset) {
            return 0;
        }

        if (preg_match_all('/\b(\d+)w\b/', $srcset, $matches)) {
            $widths = array_map('intval', $matches[1]);
            return max($widths);
        }

        return 0;
    }

    public function index(string $type, string $slug, string $id)
    {
        $safeId = trim(strtolower(strip_tags($id)));

        if (empty($safeId)) {
            return [];
        }

        if ($slug === 'Celebs') {
            $modelClass = \App\Models\ImagesCelebs::class;

            $result = $modelClass::select('id', 'id_celeb', 'src', 'srcset')
                ->where('id_celeb', $safeId)
                ->orderBy('id', 'desc')
                ->simplePaginate(200)
                ->toArray();

            $formattedData = [];
            foreach ($result['data'] as $item) {
                $srcSegment = $item['src'] ?? '';
                // Если srcset пуст, используем src как запасной вариант
                $srcsetSegment = !empty($item['srcset']) ? $item['srcset'] : $srcSegment;

                $formattedData[] = [
                    'id' => $item['id'],
                    'id_celeb' => $item['id_celeb'],
                    'src' => $srcSegment ? (str_starts_with($srcSegment, 'http') ? $srcSegment : $this->getBaseUrl() . '/original' . $srcSegment) : null,
                    'srcset' => $srcsetSegment ? (str_starts_with($srcsetSegment, 'http') ? $srcsetSegment : $this->getBaseUrl() . '/w200' . $srcsetSegment) : null,
                ];
            }

            return [
                'data_tmdb' => [], // Для актеров мы не делим на TMDB/IMDB, всё отдаем в data_imdb
                'data_imdb' => $formattedData,
                'total_tmdb' => 0,
                'total_imdb' => $result['to'] ?? count($formattedData),
                'has_more_tmdb' => false,
                'has_more_imdb' => !empty($result['next_page_url']),
            ];
        }

        if (!in_array($slug, self::ALLOWED_FILM_TYPES)) {
            return [];
        }

        $allowedTypes = ['images', 'posters'];
        if (!in_array($type, $allowedTypes)) {
            $type = 'images';
        }

        $tmdbModelClass = ($type === 'posters') ? \App\Models\TMDb\MoviePoster::class : \App\Models\TMDb\MovieBackdrop::class;
        $imdbModelClass = $this->getImdbModelClass($type, $slug);

        $tmdbResult = $tmdbModelClass::select('id', 'id_movie', 'file_path', 'width', 'height', 'locale')
            ->where('id_movie', $safeId)
            ->orderBy('id', 'desc')
            ->simplePaginate(200)
            ->toArray();

        $imdbResult = ['data' => [], 'to' => 0];
        if ($imdbModelClass) {
            $imdbResult = $imdbModelClass::select('id', 'id_movie', 'src', 'srcset')
                ->where('id_movie', $safeId)
                ->orderBy('id', 'desc')
                ->simplePaginate(200)
                ->toArray();
        }

        $formattedTmdb = $this->formatTmdbItems($tmdbResult['data'], $type);
        $formattedImdb = $this->formatImdbItems($imdbResult['data']);

        $assignData = \App\Models\AssignPoster::where('id_movie', $safeId)->first();

        if ($assignData) {
            $assignArray = [
                'id_poster_original'     => $assignData->id_poster_original,
                'id_poster_ru'           => $assignData->id_poster_ru,
                'id_posters_characters'  => $assignData->id_posters_characters,
                'id_posters_alternative' => $assignData->id_posters_alternative,
                'id_wallpaper'           => $assignData->id_wallpaper,
            ];

            $result = [
                'data_tmdb'       => $formattedTmdb,
                'data_imdb'       => $formattedImdb,
                'total_tmdb'      => $tmdbResult['to'] ?? 0,
                'total_imdb'      => $imdbResult['to'] ?? 0,
                'has_more_tmdb'   => !empty($tmdbResult['next_page_url']),
                'has_more_imdb'   => !empty($imdbResult['next_page_url']),
                'poster_count'    => [
                    'id_poster_original'     => $assignData->id_poster_original ? 1 : 0,
                    'id_poster_ru'           => $assignData->id_poster_ru ? 1 : 0,
                    'id_posters_characters'  => count(json_decode($assignData->id_posters_characters ?? '[]', true) ?? []),
                    'id_posters_alternative' => count(json_decode($assignData->id_posters_alternative ?? '[]', true) ?? []),
                    'id_wallpaper'           => $assignData->id_wallpaper ? 1 : 0,
                ],
                'locale'          => [
                    'id_poster_original'     => __('buttons.original'),
                    'id_poster_ru'           => __('buttons.russian'),
                    'id_posters_characters'  => __('buttons.characters'),
                    'id_posters_alternative' => __('buttons.alternative'),
                    'id_wallpaper'           => __('buttons.wallpaper'),
                ],
                'assigned_ids'    => $assignArray,
            ];

            if ($type === 'posters') {
                foreach ($result['data_tmdb'] as &$item) {
                    $item['status_poster'] = $this->checkAssignPoster($item['id'], $assignArray);
                }
                foreach ($result['data_imdb'] as &$item) {
                    $item['status_poster'] = $this->checkAssignPoster($item['id'], $assignArray);
                }
            }
        } else {
            $result = [
                'data_tmdb'       => $formattedTmdb,
                'data_imdb'       => $formattedImdb,
                'total_tmdb'      => $tmdbResult['to'] ?? 0,
                'total_imdb'      => $imdbResult['to'] ?? 0,
                'has_more_tmdb'   => !empty($tmdbResult['next_page_url']),
                'has_more_imdb'   => !empty($imdbResult['next_page_url']),
            ];
        }

        return $result;
    }

    private function formatTmdbItems(array $data, string $type): array
    {
        foreach ($data as &$item) {
            $filePath = $item['file_path'] ?? '';

            $item['src']    = $filePath ? $this->getBaseUrl() . '/' . self::ORIGINAL_SIZE . $filePath : null;
            $item['srcset'] = $filePath ? $this->getBaseUrl() . '/' . self::PREVIEW_SIZE . $filePath : null;

            $item['width']  = $item['width'] ?? 0;
            $item['height'] = $item['height'] ?? 0;
            $item['locale'] = $item['locale'] ?? 'null';
            $item['source'] = 'tmdb';
            unset($item['file_path']);
        }
        return $data;
    }

    private function formatImdbItems(array $data): array
    {
        foreach ($data as &$item) {
            $srcSegment = $item['src'] ?? '';
            $srcsetSegment = $item['srcset'] ?? $srcSegment;

            $item['src']    = $srcSegment ?? null;
            $item['srcset'] = $srcsetSegment ?? null;

            $item['width']  = $this->parseWidthFromSrcset($srcsetSegment);
            $item['height'] = 'null';
            $item['locale'] = 'null';
            $item['source'] = 'imdb';
        }
        return $data;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_movie'   => 'required|string|regex:/^tt\d{7,10}$/',
            'type_film'  => 'required|string|in:' . implode(',', self::ALLOWED_FILM_TYPES),
            'poster_cat' => 'required|string|in:' . implode(',', self::ALLOWED_POSTER_CATEGORIES),
            'id_poster'  => 'required|array|min:1',
            'id_poster.*' => 'nullable|integer',
        ]);

        $posterIds = $validated['id_poster'];

        switch ($validated['poster_cat']) {
            case 'id_posters_characters':
            case 'id_posters_alternative':
                $valueToSave = json_encode(array_filter($posterIds, fn($id) => !is_null($id)));
                break;
            default:
                $valueToSave = $posterIds[0] ?? null;
                break;
        }

        $typeFilmId = getTableSegmentOrTypeId($validated['type_film']);

        AssignPoster::updateOrCreate(
            ['id_movie' => $validated['id_movie']],
            [
                $validated['poster_cat'] => $valueToSave,
                'type_film' => $typeFilmId,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Постер успешно назначен']);
    }

    public function showImages(string $id)
    {
        $safeId = trim(strip_tags($id));
        if (empty($safeId)) return [];

        $backdrops = MovieBackdrop::select('id', 'id_movie', 'file_path', 'locale', 'width', 'height')
            ->where('id_movie', $safeId)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        if ($backdrops->isEmpty()) return [];

        $result = [];
        foreach ($backdrops as $backdrop) {
            $filePath = $backdrop->file_path ?? '';
            $result[] = [
                'id'       => $backdrop->id,
                'id_movie' => $backdrop->id_movie,
                'src'      => $filePath ? $this->getBaseUrl() . '/' . self::ORIGINAL_SIZE . $filePath : null,
                'srcset'   => $filePath ? $this->getBaseUrl() . '/' . self::PREVIEW_SIZE . $filePath : null, // 👈 Оптимизировано
                'width'    => $backdrop->width,
                'height'   => $backdrop->height,
                'locale'   => $backdrop->locale,
            ];
        }
        return $result;
    }

    public function showPosters(string $idMovie)
    {
        $safeId = trim(strip_tags($idMovie));
        if (empty($safeId)) return [];

        $assignData = AssignPoster::where('id_movie', $safeId)->first();
        if (!$assignData) return [];

        $posterIds = [];
        $fields = ['id_poster_original', 'id_poster_ru', 'id_posters_characters', 'id_posters_alternative', 'id_wallpaper'];

        foreach ($fields as $field) {
            $value = $assignData->$field;
            if ($value) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $posterIds = array_merge($posterIds, $decoded);
                    }
                } else {
                    $posterIds[] = $value;
                }
            }
        }

        $posterIds = array_unique(array_filter($posterIds));
        if (empty($posterIds)) return [];

        $posters = MoviePoster::select('id', 'id_movie', 'file_path', 'locale', 'width', 'height')
            ->whereIn('id', $posterIds)
            ->get();

        if ($posters->isEmpty()) return [];

        $result = [];
        foreach ($posters as $poster) {
            $filePath = $poster->file_path ?? '';
            $result[] = [
                'id'       => $poster->id,
                'id_movie' => $poster->id_movie,
                'src'      => $filePath ? $this->getBaseUrl() . '/' . self::ORIGINAL_SIZE . $filePath : null,
                'srcset'   => $filePath ? $this->getBaseUrl() . '/' . self::PREVIEW_SIZE . $filePath : null, // 👈 Оптимизировано
                'width'    => $poster->width,
                'height'   => $poster->height,
                'locale'   => $poster->locale,
            ];
        }
        return $result;
    }

    private function checkAssignPoster($id, ?array $assignArray = null): ?string
    {
        if (empty($assignArray)) return null;

        foreach ($assignArray as $key => $item) {
            if ($item === null) continue;

            switch ($key) {
                case 'id_poster_original':
                    if ($item == $id) return __('buttons.original');
                    break;
                case 'id_poster_ru':
                    if ($item == $id) return __('buttons.russian');
                    break;
                case 'id_posters_characters':
                    $decodeArr = json_decode($item, true);
                    if (!empty($decodeArr) && in_array($id, $decodeArr)) return __('buttons.characters');
                    break;
                case 'id_posters_alternative':
                    $decodeArr = json_decode($item, true);
                    if (!empty($decodeArr) && in_array($id, $decodeArr)) return __('buttons.alternative');
                    break;
                case 'id_wallpaper':
                    if ($item == $id) return __('buttons.wallpaper');
                    break;
            }
        }
        return null;
    }

    public function moveImagesToPosters(Request $request): array
    {
        $validated = $request->validate([
            'slug'   => 'required|string|in:' . implode(',', self::ALLOWED_FILM_TYPES),
            'data'   => 'required|array|min:1',
            'data.*' => 'integer',
        ]);

        return DB::transaction(function () use ($validated) {
            $tmdbSource = MovieBackdrop::class;
            $tmdbTarget = MoviePoster::class;
            $imdbSource = $this->getImdbModelClass('images', $validated['slug']);
            $imdbTarget = $this->getImdbModelClass('posters', $validated['slug']);

            $tmdbRecords = $tmdbSource::whereIn('id', $validated['data'])
                ->get(['id_movie', 'file_path', 'locale', 'width', 'height'])
                ->toArray();

            $imdbRecords = [];
            if ($imdbSource) {
                $imdbRecords = $imdbSource::whereIn('id', $validated['data'])
                    ->get(['id_movie', 'src', 'srcset'])
                    ->toArray();
            }

            if (empty($tmdbRecords) && empty($imdbRecords)) {
                return ['success' => false, 'message' => 'No records found to move', 'moved_count' => 0];
            }

            $tmdbInsertData = $tmdbRecords;

            foreach ($imdbRecords as $r) {
                // Аккуратная замена с использованием env
                $baseUrl = rtrim(env('TMDB_IMAGE_BASE_URL' ).'/t/p', '/');
                $filePath = str_replace($baseUrl . '/original', '', $r['src']);

                if ($filePath && $filePath !== $r['src']) {
                    $tmdbInsertData[] = [
                        'id_movie'  => $r['id_movie'],
                        'file_path' => $filePath,
                        'locale'    => null,
                        'width'     => 0,
                        'height'    => 0,
                    ];
                }
            }

            if (!empty($tmdbInsertData)) {
                $tmdbTarget::insertOrIgnore($tmdbInsertData);
            }

            $imdbInsertData = $imdbRecords;

            foreach ($tmdbRecords as $r) {
                $imdbInsertData[] = [
                    'id_movie' => $r['id_movie'],
                    'src'      => $this->getBaseUrl() . '/original' . $r['file_path'],
                    'srcset'   => $this->getBaseUrl() . '/w185' . $r['file_path'],
                ];
            }

            if ($imdbTarget && !empty($imdbInsertData)) {
                $imdbTarget::insertOrIgnore($imdbInsertData);
            }

            $tmdbSource::whereIn('id', $validated['data'])->delete();
            if ($imdbSource) {
                $imdbSource::whereIn('id', $validated['data'])->delete();
            }

            return [
                'success'     => true,
                'moved_count' => count($tmdbInsertData) + count($imdbInsertData),
            ];
        });
    }

    public function reorderImages(Request $request)
    {
        $validated = $request->validate([
            'movie_id'      => 'required|string|regex:/^tt\d{7,10}$/',
            'ordered_ids'   => 'required|array|min:1',
            'ordered_ids.*' => 'integer',
            'slug'          => 'required|string|in:' . implode(',', self::ALLOWED_FILM_TYPES),
        ]);

        return DB::transaction(function () use ($validated) {
            $movieId    = $validated['movie_id'];
            $orderedIds = $validated['ordered_ids'];

            $tmdbModel = MovieBackdrop::class;
            $imdbModel = $this->getImdbModelClass('images', $validated['slug']);

            $totalInserted   = 0;
            $insertedRecords = [];

            // TMDB
            $tmdbImages = $tmdbModel::whereIn('id', $orderedIds)
                ->where('id_movie', $movieId)
                ->get(['id', 'id_movie', 'file_path', 'locale', 'width', 'height', 'created_at'])
                ->keyBy('id')
                ->toArray();

            if (!empty($tmdbImages)) {
                $tmdbModel::whereIn('id', $orderedIds)->where('id_movie', $movieId)->delete();
                $now = now();

                foreach ($orderedIds as $id) {
                    if (!isset($tmdbImages[$id])) continue;
                    $record = $tmdbImages[$id];

                    $new = new $tmdbModel();
                    $new->id_movie   = $record['id_movie'];
                    $new->file_path  = $record['file_path'];
                    $new->locale     = $record['locale'];
                    $new->width      = $record['width'];
                    $new->height     = $record['height'];
                    $new->created_at = $record['created_at'] ?? $now;
                    $new->updated_at = $now;
                    $new->save();

                    $totalInserted++;
                    $insertedRecords[] = [
                        'id'       => $new->id,
                        'id_movie' => $new->id_movie,
                        'src'      => $this->getBaseUrl() . '/' . self::ORIGINAL_SIZE . $new->file_path,
                        'srcset'   => $this->getBaseUrl() . '/' . self::PREVIEW_SIZE . $new->file_path,
                        'width'    => $new->width,
                        'height'   => $new->height,
                        'locale'   => $new->locale ?? 'null',
                        'source'   => 'tmdb',
                    ];
                }
            }

            // IMDb
            if ($imdbModel) {
                $imdbImages = $imdbModel::whereIn('id', $orderedIds)
                    ->where('id_movie', $movieId)
                    ->get(['id', 'id_movie', 'src', 'srcset', 'created_at'])
                    ->keyBy('id')
                    ->toArray();

                if (!empty($imdbImages)) {
                    $imdbModel::whereIn('id', $orderedIds)->where('id_movie', $movieId)->delete();
                    $now = now();

                    foreach ($orderedIds as $id) {
                        if (!isset($imdbImages[$id])) continue;
                        $record = $imdbImages[$id];

                        $new = new $imdbModel();
                        $new->id_movie   = $record['id_movie'];
                        $new->src        = $record['src'];
                        $new->srcset     = $record['srcset'];
                        $new->created_at = $record['created_at'] ?? $now;
                        $new->updated_at = $now;
                        $new->save();

                        $totalInserted++;
                        $insertedRecords[] = [
                            'id'       => $new->id,
                            'id_movie' => $new->id_movie,
                            'src'      => $new->src,
                            'srcset'   => $new->srcset,
                            'width'    => $this->parseWidthFromSrcset($new->srcset),
                            'height'   => 'null',
                            'locale'   => 'null',
                            'source'   => 'imdb',
                        ];
                    }
                }
            }

            if ($totalInserted === 0) {
                return response()->json(['success' => false, 'message' => 'No images found to reorder', 'images' => []], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order saved successfully',
                'images'  => $insertedRecords,
            ]);
        });
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'type'   => 'required|string|in:images,posters',
            'slug'   => 'required|string|in:' . implode(',', self::ALLOWED_FILM_TYPES),
            'data'   => 'required|array|min:1',
            'data.*' => 'integer',
        ]);

        $tmdbModelClass = ($validated['type'] === 'posters') ? MoviePoster::class : MovieBackdrop::class;
        $imdbModelClass = $this->getImdbModelClass($validated['type'], $validated['slug']);

        $deletedTmdb = $tmdbModelClass::whereIn('id', $validated['data'])->delete();
        $deletedImdb = $imdbModelClass ? $imdbModelClass::whereIn('id', $validated['data'])->delete() : 0;

        return response()->json([
            'success'       => true,
            'deleted_count' => $deletedTmdb + $deletedImdb,
        ]);
    }
}
