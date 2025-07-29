<?php

namespace App\Http\Controllers;

use App\Services\ExportService;
use Illuminate\Http\Request;

class AdminTagsController extends Controller
{
    protected $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }
    public function send(Request $request)
    {
        try {
            $result = $this->exportService->exportTags();
            return response()->json(['data' => $result], $result['status']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
