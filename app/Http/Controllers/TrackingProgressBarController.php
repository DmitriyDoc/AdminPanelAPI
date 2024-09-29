<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class TrackingProgressBarController
{
    private $dashboardCountTable = 9;

    public function trackingProgressBar($key)
    {
        if (session()->has('tracking.'.$key)) {
            $currPercent = session()->get('tracking');
            if ($key == 'dashboardPercentageBar'){
                $currPercent['dashboardPercentageBar'] = ceil( ($currPercent['dashboardPercentageBar'] * 100) / $this->dashboardCountTable) ?? 0;
            }
            if ($currPercent[$key] >= 100){
                session()->forget('tracking.'.$key);
            }
        }
        return $currPercent ?? [];
    }
    public function requestSessionKey (Request $request)
    {
        $sessionKey = $request->get('sesKey');
        return $this->trackingProgressBar($sessionKey ?? null);
    }
}
