<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Events\DashboardCurrentPercentageEvent;

class DashboardController
{
    private $dashboardCountTable = 9;
    public function index()
    {
//        Redis::set('name','test');
//        dd(Redis::get('name'));
//        Cache::put('some',1);
//        dd(Cache::get('some'));
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
            $progressBarTotal = ceil( (($index++ + 1) * 100) / $this->dashboardCountTable) ?? 0 ;
            event(new DashboardCurrentPercentageEvent($progressBarTotal));
        }
        if (!empty($data)){
            $data['locale'] = LanguageController::localizingDashboardInfo();
        }
        return $data;
    }

    public function test(\Illuminate\Http\Request $request){
        dd(1);
        return [];
    }

}
