<?php


namespace App\Http\Controllers\Parser;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ParserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class ParserStartController
{
    public function parseInitStore(Request $request)
    {
        Redis::flushDB();
        session()->forget('tracking.report');
        session()->put('tracking.report.start', __('parser.start_parser'));
        session()->save();
        $validator = Validator::make($request->all()['data'],[
            'flag' => 'required|boolean',
            'date_from' => 'date_format:"Y-m-d"',
            'date_till' => 'date_format:"Y-m-d"|after_or_equal:date_from',
            'movie_types' => 'array|max:8',
            'sort' => 'required|string|max:15',
            'persons_source' => 'array|max:40',
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
            $data['flag'] ? $parserStart->parseMoviesByPeriod($data['movie_types']) : $parserStart->parsePersons($data['persons_source'],$data['switch_new_update']);
            session()->put('tracking.report.stop',  __('parser.completed_parser'));
        }
    }

    public function parseMovieUpdate(Request $request)
    {
        Redis::flushDB();
        (new ParserUpdateMovieController())->update($request);
    }
    public function parseCelebUpdate(Request $request)
    {
        Redis::flushDB();
        (new ParserUpdateCelebController())->update($request);
    }
    public function parseLocalization()
    {
        return LanguageController::localizingParserInfo();
    }
}
