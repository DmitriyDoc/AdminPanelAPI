<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PlayerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BalancerController extends Controller {
    protected $playerService;

    public function __construct(PlayerService $playerService)
    {
        $this->playerService = $playerService;
    }
    public function getMovieByImdb(Request $request): JsonResponse
    {
        $request->validate([
            'imdb' => 'required|string'
        ]);

        try {
            $playerData = $this->playerService->getAllohaPlayer(['imdb' => $request->input('imdb')]);
            if (!$playerData['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Фильм не найден или API вернул некорректные данные'
                ], 404);
            }
            return response()->json($playerData);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
