<?php

namespace App\Http\Controllers;

use App\Services\ExportService;
use Illuminate\Http\Request;

class AdminTaxonomyController extends Controller
{
    protected $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }
    public function send(Request $request)
    {
        try {
            $result = $this->exportService->exportTaxonomies();
            return response()->json(['data' => $result], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
