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
    public function trackingProgressBar($key)
    {
        if (session()->has($key)) {
            $currPercent = session()->get($key);
            if ($currPercent >= 100){
                session()->forget($key);
            }
        }
        return $currPercent ?? 0;
    }
    public function requestSessionKey (Request $request)
    {
        $sessionKey = $request->route('sesKey');
        return $this->trackingProgressBar($sessionKey ?? null);
    }

}
