<?php

namespace App\Http\Controllers\TMDb;

use App\Http\Controllers\Controller;
use App\Services\TMDb\TmdbImagesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TmdbImagesController extends Controller
{
    public function getTMDBImages(Request $request, TmdbImagesService $tmdbImagesService)
    {
        $validated = $request->validate([
            'imdb_id' => 'required|string|regex:/^tt\d{7,}$/',
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        try {
            $imdbId = $validated['imdb_id'];
            $offset = $validated['offset'] ?? 0;
            $limit = $validated['limit'] ?? 10;

            $images = $tmdbImagesService->getImagesByImdb($imdbId, $offset, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'tmdb_id' => $images['tmdb_id'],
                    'type' => $images['type'],
                    'title' => $images['title'],
                    'logos' => $images['logos'],
                    'backdrops' => $images['backdrops'],
                    'pagination' => [
                        'total' => $images['total_backdrops'],
                        'offset' => $offset,
                        'limit' => $limit,
                        'loaded' => $offset + count($images['backdrops']),
                        'hasMore' => ($offset + $limit) < $images['total_backdrops']
                    ]
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('TMDB Images Fetch Error', [
                'imdb_id' => $request->input('imdb_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Не удалось получить изображения. Попробуйте позже.'
            ], 500);
        }
    }
}
