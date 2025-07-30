<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Collection;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use App\Models\LocalizingFranchise;
use App\Models\MovieInfo;
use App\Models\Tag;
use App\Models\TagsMoviesPivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExportService
{
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
                'label' => $category->label,
                'value' => $category->value,
                'title_en' => $category->title_en,
                'title_ru' => $category->title_ru,
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
