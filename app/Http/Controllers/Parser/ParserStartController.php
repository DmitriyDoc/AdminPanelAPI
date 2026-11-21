<?php

namespace App\Http\Controllers\Parser;

use App\Events\ParserReportEvent;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ParserController;
use App\Jobs\ParseMovieUpdateJob;
use App\Jobs\ParseCelebUpdateJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ParserStartController
{
    public function parseInitStore(Request $request)
    {
        $validator = Validator::make($request->all()['data'],[
            'flag' => 'required|boolean',
            'date_from' => 'date_format:"Y-m-d"',
            'date_till' => 'date_format:"Y-m-d"|after_or_equal:date_from',
            'movie_types' => 'array|max:8',
            'sort' => 'required|string|max:15',
            'persons_source' => 'array|max:20',
            'type_images' => 'required|string|max:15',
            'type_posters' => 'max:10',
            'switch_new_update' => 'boolean',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        if ($data = $validator->getData()){
            $parserStart = new ParserController($data);

            ParserController::$reportProgress['report']['icon'] = 'info';
            ParserController::$reportProgress['report']['status'] = __('parser.start_parser');

            event(new ParserReportEvent(ParserController::$reportProgress));
            $data['flag'] ? $parserStart->parseMoviesByPeriod($data['movie_types']) : $parserStart->parsePersons($data['persons_source'],$data['switch_new_update']);

            ParserController::$reportProgress['report']['icon'] = 'success';
            ParserController::$reportProgress['report']['status'] = __('parser.completed_parser');
            ParserController::$reportProgress['report']['statusBar']['percent'] = 100;
            ParserController::$reportProgress['report']['statusBar']['action'] = __('parser.finish_parser');
            ParserController::$reportProgress['report']['statusBar']['color'] = 'success';
            event(new ParserReportEvent(ParserController::$reportProgress));
        }
    }

    public function parseMovieUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'data.id' => 'required|string|regex:/^tt\d{7,10}$/',
                'data.type' => 'required|string',
                'data.posterType' => 'required|string',
                'data.typeSync' => 'required|string',
            ]);

            $movieData = $validated['data'];
            ParseMovieUpdateJob::dispatch(
                movieData: [
                    'id' => $movieData['id'],
                    'posterType' => $movieData['posterType'],
                    'typeSync' => $movieData['typeSync'],
                ],
                type: $movieData['type']
            )->onQueue('parsing_movie');

            return response()->json(['status' => 'queued']);

        } catch (ValidationException $e) {
            Log::error("Validation Failed in parseMovieUpdate", [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        }
    }

    public function parseCelebUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'data.id' => 'required|string|regex:/^nm\d{7,10}$/',
                'data.imageType' => 'required|string',
            ]);

            $celebData = $validated['data'];

            ParseCelebUpdateJob::dispatch([
                'id' => $celebData['id'],
                'posterType' => $celebData['imageType'],
            ])->onQueue('parsing_celeb');

            return response()->json(['status' => 'queued']);

        } catch (ValidationException $e) {
            Log::error("Validation Failed in parseCelebUpdate", [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            throw $e;
        }
    }

    public function parseCharts(Request $request)
    {
        // 🔧 ИСПРАВЛЕНИЕ: Используем массив для правил с regex, содержащим |
        $validator = Validator::make($request->all()['data'] ?? [], [
            'action' => 'required|string|in:update_all,update_one',
            'chart_id' => [
                'nullable',
                'string',
                'regex:/^(in)\d{7,10}$/'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $parserStart = new ParserController();

        try {
            switch ($data['action']) {
                case 'update_all':
                    // Запускаем парсинг всех списков
                    $parserStart->parseAllCharts();

                    return response()->json([
                        'status' => 'success',
                        'message' => __('parser.charts_update_all_queued')
                    ]);

                case 'update_one':
                    // Обновляем конкретный список по ID
                    if (!empty($data['chart_id'])) {
                        $parserStart->parseChart($data['chart_id']);

                        return response()->json([
                            'status' => 'success',
                            'message' => __('parser.chart_update_queued', ['id' => $data['chart_id']])
                        ]);
                    }
                    break;
            }

            return response()->json([
                'status' => 'error',
                'message' => __('parser.invalid_action')
            ], 400);

        } catch (\Exception $e) {
            Log::error("Error in parseCharts", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function parseLocalization()
    {
        return LanguageController::localizingParserInfo();
    }
}
