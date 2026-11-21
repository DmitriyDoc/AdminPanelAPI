<?php

namespace App\Http\Controllers\Resize;

use App\Http\Controllers\Controller;
use App\Services\Resize\ImageResizeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResizeImagesController extends Controller
{
    public function __construct(
        private ImageResizeService $resizeService
    ) {}

    public function index(Request $request)
    {
        ini_set('memory_limit', '1G');
        ini_set('max_execution_time', '1800');
        ini_set('max_input_time', '1800');
        set_time_limit(1800);

        $originalImdbId = $request->get('id_movie');
        $slug = $request->get('slug');

        if (!preg_match('/^tt\d{7,10}$/', $originalImdbId)) {
            return response()->json(['success' => false, 'message' => 'Invalid movie ID format.'], 400);
        }

        if (empty($slug)) {
            return response()->json(['success' => false, 'message' => 'Movie slug is required.'], 400);
        }

        try {
            $this->resizeService->process($originalImdbId, $slug);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Images processed and sent to Media API successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error("RESIZE-FAILED--ID--{$originalImdbId} ---> ERROR: {$e->getMessage()}");
            return response()->json(['success' => false, 'message' => 'Internal error during image processing.'], 500);
        }
    }
}
