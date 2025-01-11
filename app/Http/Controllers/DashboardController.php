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

            $data['data'][$index]['key'] = $index.'_'.$name;
            $data['data'][$index]['title'] =  $name !== 'Celebs' ? __('movies.type_movies.'.$name) : __('movies.celebs');
            $data['data'][$index]['count'] = $count;
            $data['data'][$index]['lastAddCount'] = $update;

            session()->increment('tracking.dashboardPercentageBar',1);
            session()->save();
        }
        if (!empty($data)){
            $data['locale'] = LanguageController::localizingDashboardInfo();
        }
        return $data;
    }

    public function test(){
        dd('test');
    }

}
