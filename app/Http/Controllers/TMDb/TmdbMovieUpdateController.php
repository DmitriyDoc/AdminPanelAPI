<?php

namespace App\Http\Controllers\TMDb;

use App\Http\Controllers\Controller;
use App\Jobs\TmdbMovieUpdateJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TmdbMovieUpdateController extends Controller
{
    public function parseMovieUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'data.id' => 'required|string|regex:/^tt\d{7,10}$/',
                'data.type' => 'required|string',
                'data.typeSync' => 'required|string|in:posters_sync,images_sync,info_sync,full_sync,import_sync',
            ]);

            TmdbMovieUpdateJob::dispatch(
                movieData: [
                    'id' => $validated['data']['id'],
                    'posterType' => $validated['data']['posterType'] ?? 'poster',
                    'typeSync' => $validated['data']['typeSync'],
                ],
                type: $validated['data']['type']
            )->onQueue('parsing_movie');

            return response()->json(['status' => 'queued', 'message' => 'TMDB update job dispatched']);

        } catch (ValidationException $e) {
            Log::error("TMDB Validation Failed in parseMovieUpdate", [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        }
    }
}
