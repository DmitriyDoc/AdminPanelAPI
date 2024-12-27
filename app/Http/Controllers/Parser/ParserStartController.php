<?php


namespace App\Http\Controllers\Parser;
use App\Http\Controllers\ParserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ParserStartController
{
    public function parseInit(Request $request)
    {
        session()->forget('tracking.report');
        session()->put('tracking.report.start', "PARSER START");
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
            //$parserStart->actualizeYearTitleForTableIdType($data['movie_types']);
            session()->put('tracking.report.stop', "PARSING COMPLETED SUCCESSFULY!");
        }
    }

    public function parseMovieUpdate(Request $request){
        (new ParserUpdateMovieController())->update($request);
    }
    public function parseCelebUpdate(Request $request){
        (new ParserUpdateCelebController())->update($request);
    }
}
