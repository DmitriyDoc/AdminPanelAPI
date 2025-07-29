<?php

namespace App\Http\Controllers;

use App\Services\ExportService;
use Illuminate\Http\Request;

class AdminMovieController extends Controller
{
    protected $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }
    public function send(Request $request)
    {
        $request->validate([
            'limit' => 'integer|min:1|max:1000',
        ]);

        $limit = $request->input('limit', 500);
        $result = $this->exportService->exportMovies($limit);
        try {
            return response()->json(['data' => $result], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
