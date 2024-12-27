<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LangsController
{
    public function changeLocale(Request $request){
        if (! in_array($locale = $request->query('lang','en'), ['en', 'ru'])) {
            abort(400);
        }
        App::setLocale($locale);
    }
}
