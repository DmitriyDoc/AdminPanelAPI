<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrackingProgressBarController
{
    public function trackingDashboard(Request $request)
    {
        $totalCountTable = 9;
        $value = null;
        if ($request->session()->has('dashboardPercentageBar')) {
            $value = $request->session()->get('dashboardPercentageBar');
        }
        $currPercent = $value ? ceil( ($value * 100) / $totalCountTable): 0;
        //Log::info('PERCENT--->>',[$currPercent]);
        if ($currPercent >= 100){
            $request->session()->forget('dashboardPercentageBar');
        }
        return $currPercent;
    }
    public function trackingSyncMovie(Request $request)
    {
        if ($request->session()->has('syncMoviePercentageBar')) {
            $currPercent = $request->session()->get('syncMoviePercentageBar');
            //Log::info('PERCENT--->>',[$currPercent]);
            if ($currPercent >= 100){
                $request->session()->forget('syncMoviePercentageBar');
            }
        }
        return $currPercent ?? 0;
    }
    public function trackingSyncPerson(Request $request)
{
    if ($request->session()->has('syncPersonPercentageBar')) {
        $currPercent = $request->session()->get('syncPersonPercentageBar');
        if ($currPercent >= 100){
            $request->session()->forget('syncPersonPercentageBar');
        }
    }
    return $currPercent ?? 0;
}
}
