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
        try {
            $request->validate([
                'switch_all' => 'boolean',
            ]);
            $switchAll = $request->input('switch_all', false);
            $result = $this->exportService->exportMovies($switchAll);
            return response()->json(['data' => $result], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
