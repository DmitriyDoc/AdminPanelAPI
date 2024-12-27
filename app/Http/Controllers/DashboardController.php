<?php

namespace App\Http\Controllers;

use App\Models\CelebsInfo;
use App\Models\MovieInfo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    private $dataMovie = [
        'genres' => null,
        'cast' => null,
        'directors' => null,
        'writers' => null,
        'story_line' => null,
        'release_date' => null,
        'countries' => null,
    ];
    public function index()
    {
        $data = [];
        $allowedTableNames = [
            0=>'FeatureFilm',
            1=>'MiniSeries',
            2=>'ShortFilm',
            3=>'TvMovie',
            4=>'TvSeries',
            5=>'TvShort',
            6=>'TvSpecial',
            7=>'Video',
            8=>'Celebs',
        ];

        session()->forget('tracking.dashboardPercentageBar');
        session(['tracking.dashboardPercentageBar' => 0]);
        session()->save();

        $model = modelByName('MovieInfo');
        foreach ($allowedTableNames as $index => $name) {
            if ($name !== 'Celebs'){
                $count = $model::where('type_film',getTableSegmentOrTypeId($name))->count();
                $update = $model::where('type_film',getTableSegmentOrTypeId($name))->where('created_at','>=',Carbon::now()->subdays(1))->count();
            } else {
                $model = modelByName('CelebsInfo');
                $count = $model->count();
                $update = $model::where('created_at','>=',Carbon::now()->subdays(1))->count();
            }

            $data[$index]['key'] = $index.'_'.$name;
            $data[$index]['title'] = $name;
            $data[$index]['count'] = $count;
            $data[$index]['lastAddCount'] = $update;

            session()->increment('tracking.dashboardPercentageBar',1);
            session()->save();
        }
        return [
            'success' => true,
            'status' => 200,
            'data' => $data
        ];
    }

    public function test(){
        return;
    }

}
