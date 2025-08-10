<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class Localization
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('current_locale')) {
            $locale = session()->get('current_locale');
        }
        else {
            $locale = app()->currentLocale();
            session()->put('current_locale', $locale);
        }
        app()->setLocale($locale);
        return $next($request);
    }
}
