<?php

namespace App\Http\Controllers;


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

        foreach ($allowedTableNames as $index => $name) {
            $model = convertVariableToModelName('IdType',$name, ['App', 'Models']);
            $data[$index]['key'] = $index.'_'.$name;
            $data[$index]['title'] = $name;
            $data[$index]['count'] = $model->getCountAttribute();
            $data[$index]['lastAddCount'] = $model->getLastDayCountAttribute();
            session()->increment('tracking.dashboardPercentageBar',1);
            session()->save();
        }
        return [
            'success' => true,
            'status' => 200,
            'data' => $data
        ];
    }

}
