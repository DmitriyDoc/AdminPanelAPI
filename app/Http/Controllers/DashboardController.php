<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        foreach ($allowedTableNames as $index => $name) {
            $model = convertVariableToModelName('IdType',$name, ['App', 'Models']);
            $data[$index]['key'] = $index.'_'.$name;
            $data[$index]['title'] = $name;
            $data[$index]['count'] = $model->getCountAttribute();
            $data[$index]['lastAddCount'] = $model->getLastDayCountAttribute();
        }
        return [
            'success' => true,
            'status' => 200,
            'data' => $data
        ];
    }
}
