<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
class AdminBackupController extends Controller
{
    public function sendFullTaxonomies(Request $request) {
        $result = app(\App\Services\ExportService::class)->exportFullTaxonomies();
        return response()->json($result, $result['status'] ?? 200);
    }
    public function sendFullTags(Request $request) {
        $result = app(\App\Services\ExportService::class)->exportFullTags();
        return response()->json($result, $result['status'] ?? 200);
    }
}
