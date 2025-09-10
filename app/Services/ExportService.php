<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use App\Models\MovieCategory;
use App\Models\MovieInfo;
use App\Models\Tag;
use App\Models\TagsMoviesPivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportService
{

    public function exportCollections()
    {
        $collectionsDir = 'collections';
        $collectionsDirs = Storage::disk('public')->directories($collectionsDir);
        $formData = [];

        if (empty($collectionsDirs)) {
            Log::error('No collection directories found in storage/public/collections');
            return response()->json(['success' => false, 'message' => 'No collections directories found'], 422);
        }

        foreach ($collectionsDirs as $colDir) {
            $collectionId = basename($colDir);

            $subDirs = [
                'coverCol' => "collections/{$collectionId}/cover",
            ];

            foreach ($subDirs as $field => $path) {
                $files = Storage::disk('public')->files($path);
                if (empty($files)) {
                    Log::warning("No files found in {$path} for collection {$collectionId}");
                    continue;
                }

                foreach ($files as $file) {
                    $filePath = Storage::disk('public')->path($file);
                    if (!is_readable($filePath)) {
                        Log::error("File is not readable: {$filePath}");
                        continue;
                    }

                    $filename = basename($file);
                    $formData[] = [
                        'name' => "collections[{$collectionId}][{$field}][]",
                        'contents' => file_get_contents($filePath),
                        'filename' => $filename,
                    ];
                }
            }
        }

        if (empty($formData)) {
            Log::error('No files to export');
            return response()->json(['success' => false, 'message' => 'No files to export'], 422);
        }

//        Log::info('Collections data prepared for export:', [
//            'collections' => array_map('basename', $collectionsDirs),
//            'files_count' => count($formData)
//        ]);

        try {
            $response = Http::withToken(config('services.kinospectr.api_token'))
                ->asMultipart()
                ->post(config('services.kinospectr.api_url') . '/api/collections/import', $formData);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'type' => 'success',
                    'message' => $response->json('message', 'Collections imported successfully.')
                ]);
            } else {
                Log::error('Failed to export collections', ['response' => $response->json()]);
                return response()->json([
                    'success' => false,
                    'type' => 'error',
                    'message' => 'Failed to export collections: ' . ($response->json('message', 'Unknown error'))
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error exporting collections: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'type' => 'error',
                'message' => 'Error exporting collections: ' . $e->getMessage()
            ], 500);
        }
    }
    public function exportIndex()
    {
        $sectionsDir = 'sections';
        $sectionDirs = Storage::disk('public')->directories($sectionsDir);
        $formData = [];

        if (empty($sectionDirs)) {
            Log::error('No section directories found in storage/public/sections');
            return response()->json(['success' => false, 'message' => 'No section directories found'], 422);
        }

        foreach ($sectionDirs as $sectionDir) {
            $sectionId = basename($sectionDir);
            $subDirs = [
                'section_logo' => "sections/{$sectionId}/section_logo",
                'backdrop' => "sections/{$sectionId}/backdrop",
                'film_logo_ru' => "sections/{$sectionId}/film_logos/ru",
                'film_logo_en' => "sections/{$sectionId}/film_logos/en",
                'posters' => "sections/{$sectionId}/posters",
            ];

            foreach ($subDirs as $field => $path) {
                $files = Storage::disk('public')->files($path);
                if (empty($files)) {
                    Log::warning("No files found in {$path} for section {$sectionId}");
                    continue;
                }
                foreach ($files as $file) {
                    $filePath = Storage::disk('public')->path($file);
                    if (!is_readable($filePath)) {
                        Log::error("File is not readable: {$filePath}");
                        continue;
                    }
                    $filename = basename($file);
                    $formData[] = [
                        'name' => "sections[{$sectionId}][{$field}][]",
                        'contents' => file_get_contents($filePath),
                        'filename' => $filename,
                    ];
                }
            }
        }

        if (empty($formData)) {
            Log::error('No files to export');
            return response()->json(['success' => false, 'message' => 'No files to export'], 422);
        }

        //Log::info('Sections data prepared for export:', ['sections' => array_map('basename', $sectionDirs), 'files_count' => count($formData)]);

        try {
            $response = Http::withToken(config('services.kinospectr.api_token'))
                ->asMultipart()
                ->post(config('services.kinospectr.api_url') . '/api/sections/import', $formData);
            if ($response->successful()) {
                return response()->json(['success' => true, 'type' => 'success', 'message' => $response->json()['message'] ]);
            } else {
                Log::error('Failed to export sections', ['response' => $response->json()]);
                return response()->json(['success' => false,  'type' => 'error','message' => 'Failed to export sections: ' . ($response->json()['message'] ?? 'Unknown error')], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error exporting sections: ' . $e->getMessage());
            return response()->json(['success' => false, 'type' => 'error', 'message' => 'Error exporting sections: ' . $e->getMessage()], 500);
        }
    }
    public function exportMovies(bool $switchAll): array
    {
        return DB::transaction(function () use ($switchAll) {
            try {
                $statusMovie = $switchAll ? 2 : 1;
                $query = MovieInfo::query()
                    ->with(['localazingRu', 'localazingEn'])
                    ->where('published', $statusMovie);

                if (!$query->exists()) {
                    return [
                        'response' => null,
                        'message' => 'No movies found to export',
                        'type' => 'warning',
                        'status' => 200,
                        'success' => false,
                    ];
                }

                $results = [];
                $success = true;
                $lastResponse = null;
                $processedMovieIds = [];

                // Обработка чанками по 1000
                $query->chunk(10, function ($movies) use (&$results, &$success, &$lastResponse, &$processedMovieIds,$switchAll) {
                    $moviesData = $movies->map(function ($movie) {
                        return $this->mapMovieToKinospectrFormat($movie);
                    })->toArray();

                    $response = Http::withToken(config('services.kinospectr.api_token'))
                        ->post(config('services.kinospectr.api_url') . '/api/movies/import', [
                            'movies' => $moviesData,
                            'switchAll' => $switchAll,
                        ]);

                    if ($response->successful()) {
                        $movieIds = $movies->pluck('id')->toArray();
                        $processedMovieIds = array_merge($processedMovieIds, $movieIds);
                        $results[] = $response->json();
                        $lastResponse = $response->json();
                    } else {
                        Log::error('Failed to export movies chunk to Kinospectr', [
                            'status' => $response->status(),
                            'response' => $response->body(),
                            'movie_count' => count($moviesData),
                        ]);
                        $success = false;
                        $lastResponse = [
                            'success' => false,
                            'type' => 'error',
                            'message' => 'Failed to export movies chunk: ' . $response->body(),
                            'status' => $response->status(),
                        ];
                    }
                });

                if (!empty($processedMovieIds)) {
                    MovieInfo::whereIn('id', $processedMovieIds)->update(['published' => 2]);
                }

                if ($success) {
                    return [
                        'success' => true,
                        'body' => $lastResponse,
                        'message' => 'Movies sent successfully and marked as exported',
                        'type' => 'success',
                        'status' => 200,
                    ];
                }

                return $lastResponse;
            } catch (\Exception $e) {
                Log::error('Exception during movie export to Kinospectr', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'An error occurred: ' . $e->getMessage(),
                    'type' => 'error',
                    'status' => 500,
                ];
            }
        });
    }
    public function exportTaxonomies(bool $switchAll)
    {
        $statusMovie = $switchAll ? 2 : 1;
        $categories = Category::all()->map(function ($category) {
            return [
                'id' => $category->id,
                'index_id' => $category->index_id,
                'value' => $category->value,
                'title_en' => $category->title_en,
                'title_ru' => $category->title_ru,
            ];
        })->toArray();
        $movieCategories = MovieCategory::all()->map(function ($catMovie) {
            return [
                'imdb_id' => $catMovie->id_movie,
                'id_movie' => null,
                'id_category' => $catMovie->id_category,
            ];
        })->toArray();
        $collections = Collection::all()->map(function ($collection) {
            return [
                'id' => $collection->id,
                'category_id' => $collection->category_id,
                'value' => $collection->value,
                'label_en' => $collection->label_en,
                'label_ru' => $collection->label_ru,
                'created_at' => $collection->created_at,
                'updated_at' => $collection->updated_at,
            ];
        })->toArray();
        $franchises = LocalizingFranchise::all()->map(function ($franchise) {
            return [
                'id' => $franchise->id,
                'value' => $franchise->value,
                'label_en' => $franchise->label_en,
                'label_ru' => $franchise->label_ru,
                'created_at' => $franchise->created_at,
                'updated_at' => $franchise->updated_at,
            ];
        })->toArray();

        $collectionsCategoriesPivots = CollectionsCategoriesPivot::whereIn('id_movie', function ($query) use ($statusMovie) {
            $query->select('id_movie')
                ->from('movies_info')
                ->where('published', $statusMovie);
        })->get()->map(function ($pivot) {
            return [
                'id_movie' => $pivot->id_movie,
                'collection_id' => $pivot->collection_id,
                'franchise_id' => $pivot->franchise_id,
                'type_film' => $pivot->type_film,
                'viewed' => $pivot->viewed,
                'short' => $pivot->short,
                'adult' => $pivot->adult,
            ];
        })->toArray();

        $collectionsFranchisesPivots = CollectionsFranchisesPivot::all()->map(function ($pivot) {
            return [
                'franchise_id' => $pivot->id,
                'collection_id' => $pivot->collection_id,
            ];
        })->toArray();
        $taxonomiesData = [
            'categories' => $categories,
            'movie_categories' => $movieCategories,
            'collections' => $collections,
            'franchises' => $franchises,
            'collections_categories_pivots' => $collectionsCategoriesPivots,
            'collections_franchises_pivots' => $collectionsFranchisesPivots,
        ];
        $response = Http::withToken(config('services.kinospectr.api_token'))
            ->post(config('services.kinospectr.api_url') . '/api/taxonomies/import', [
                'taxonomies' => $taxonomiesData,
                'switchAll' => $switchAll,
            ]);

        if ($response->successful()) {
            return [
                'body' => $response->json(),
                'message' => 'Taxonomies sent successfully',
                'status' => $response->status(),
            ];
        }

        Log::error('Failed to export taxonomies to Kinospectr', [
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        throw new \Exception('Failed to export taxonomies to Kinospectr');
    }

    public function exportTags($switchAll)
    {
        $statusMovie = $switchAll ? 2 : 1;
        $tags = Tag::all()->map(function ($category) {
            return [
                'id' => $category->id,
                'value' => $category->value,
                'tag_name_en' => $category->tag_name_en,
                'tag_name_ru' => $category->tag_name_ru,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        })->toArray();

        $tagsMoviesPivots = TagsMoviesPivot::whereIn('id_movie', function ($query) use ($statusMovie){
            $query->select('id_movie')
                ->from('movies_info')
                ->where('published', $statusMovie);
        })->get()->map(function ($pivot) {
            return [
                'id_movie' => $pivot->id_movie,
                'id_tag' => $pivot->id_tag,
                'type_film' => $pivot->type_film,
            ];
        })->toArray();

        $tagsData = [
            'tags' => $tags,
            'tags_movies_pivots' => $tagsMoviesPivots,
        ];
        $response = Http::withToken(config('services.kinospectr.api_token'))
            ->post(config('services.kinospectr.api_url') . '/api/tags/import', [
                'tags' => $tagsData,
                'switchAll' => $switchAll,
            ]);

        if ($response->successful()) {
            return [
                'body' => $response->json(),
                'message' => 'Tags sent successfully',
                'status' => $response->status(),
            ];
        }

        Log::error('Failed to export tags to Kinospectr', [
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        throw new \Exception('Failed to export tags to Kinospectr');
    }
    protected function mapMovieToKinospectrFormat(MovieInfo $movie): array
    {
        $hasher = new IdHasher($movie->id_movie);
        $hashDecodeId = $hasher->getResult();

        $companies = unserialize($movie->companies);
        $companies = isset($companies['companies']) ? $companies['companies'] : [];

        $genresEn = $movie->localazingEn ? json_decode($movie->localazingEn->genres, true) : null;
        $genresRu = $movie->localazingRu ? json_decode($movie->localazingRu->genres, true) : null;
        $castEn = $movie->localazingEn ? json_decode($movie->localazingEn->cast, true) : null;
        $castRu = $movie->localazingRu ? json_decode($movie->localazingRu->cast, true) : null;
        $directorsEn = $movie->localazingEn ? json_decode($movie->localazingEn->directors, true) : null;
        $directorsRu = $movie->localazingRu ? json_decode($movie->localazingRu->directors, true) : null;
        $writersEn = $movie->localazingEn ? json_decode($movie->localazingEn->writers, true) : null;
        $writersRu = $movie->localazingRu ? json_decode($movie->localazingRu->writers, true) : null;
        $countriesEn = $movie->localazingEn ? json_decode($movie->localazingEn->countries, true) : null;
        $countriesRu = $movie->localazingRu ? json_decode($movie->localazingRu->countries, true) : null;

        return [
            'imdb_id' => $movie->id_movie,
            'active' => $movie->published,
            'type_film' => $movie->type_film,
            'hash_imdb_id' => $hashDecodeId,
            'title_en' => $movie->original_title ?? $movie->title,
            'title_ru' => $movie->title,
            'year_release' => $movie->year_release,
            'restrictions' => $movie->restrictions,
            'runtime' => $movie->runtime,
            'rating' => $movie->rating,
            'budget' => $movie->budget,
            'companies' => $companies,
            'genres_en' => $genresEn,
            'genres_ru' => $genresRu,
            'cast_en' => $castEn,
            'cast_ru' => $castRu,
            'directors_en' => $directorsEn,
            'directors_ru' => $directorsRu,
            'writers_en' => $writersEn,
            'writers_ru' => $writersRu,
            'countries_en' => $countriesEn,
            'countries_ru' => $countriesRu,
            'story_line_en' => $movie->localazingEn ? $movie->localazingEn->story_line : null,
            'story_line_ru' => $movie->localazingRu ? $movie->localazingRu->story_line : null,
            'release_date_en' => $movie->localazingEn ? $movie->localazingEn->release_date : null,
            'release_date_ru' => $movie->localazingRu ? $movie->localazingRu->release_date : null,
            'poster' => $movie->poster ?? null,
        ];
    }
}
