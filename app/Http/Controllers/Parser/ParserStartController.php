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
        $validator = Validator::make($request->all()['data'],[
            'flag' => 'required|boolean',
            'date_from' => 'date_format:"Y-m-d"',
            'date_till' => 'date_format:"Y-m-d"|after_or_equal:date_from',
            'movie_types' => 'array|max:8',
            'sort' => 'required|string|max:15',
            'persons_source' => 'array|max:13',
            'type_images' => 'required|string|max:15',
            'type_posters' => 'max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        if ($data = $validator->getData()){
            $parserStart = new ParserController($data);
            $data['flag'] ? $parserStart->parseMoviesByPeriod($data['movie_types']) : $parserStart->parsePersons($data['persons_source']);
            $parserStart->actualizeYearTitleForTableIdType($data['movie_types']);
        }
    }

    public function parseMovieUpdate(Request $request){
        (new ParserUpdateMovieController())->update($request);
    }
    public function parseCelebUpdate(Request $request){
        (new ParserUpdateCelebController())->update($request);
    }
}
