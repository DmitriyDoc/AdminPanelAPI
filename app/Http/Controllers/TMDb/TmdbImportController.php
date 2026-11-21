<?php

namespace App\Http\Controllers\TMDb;

use App\Http\Controllers\Controller;
use App\Services\TMDb\ImportService;
use Illuminate\Http\Request;

class TmdbImportController extends Controller
{
    protected $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    public function importOne(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string',
            'type' => 'required|string|in:movie,tv,person',
        ]);

        $result = $this->importService->importSingle($validated['id'], $validated['type']);

        if ($result['success']) {
            return response()->json(['message' => $result['message']], 200);
        }

        return response()->json(['message' => $result['message']], 400);
    }

    public function getLists()
    {
        return response()->json($this->importService->getAvailableLists());
    }

    public function getList(Request $request, string $type)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'page' => 'integer|min:1',
        ]);

        $result = $this->importService->browseList(
            $type,
            $validated['category'],
            $validated['page'] ?? 1
        );

        return response()->json($result);
    }

    public function importMany(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.imdb_id' => 'required|string',
            'items.*.entity_type' => 'required|string|in:movie,tv,person',
        ]);

        $result = $this->importService->importBatch($validated['items']);

        if ($result['success']) {
            return response()->json(['message' => $result['message']], 200);
        }

        return response()->json(['message' => 'Ошибка массового импорта'], 400);
    }
}
