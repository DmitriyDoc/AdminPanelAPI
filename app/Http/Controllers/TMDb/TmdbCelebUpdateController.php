<?php

namespace App\Http\Controllers\TMDb;

use App\Http\Controllers\Controller;
use App\Jobs\TmdbCelebUpdateJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TmdbCelebUpdateController extends Controller
{
    public function parseCelebUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'data.id' => 'required|string|regex:/^nm\d{7,10}$/',
            ]);

            TmdbCelebUpdateJob::dispatch(
                celebData: [
                    'id' => $validated['data']['id'],
                ]
            )->onQueue('parsing_celeb');

            return response()->json(['status' => 'queued', 'message' => 'Celeb update job dispatched']);

        } catch (ValidationException $e) {
            Log::error("Celeb Validation Failed in parseCelebUpdate", [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        }
    }
}
