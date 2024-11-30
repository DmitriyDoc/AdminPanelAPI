<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAPIAccess
{

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() !== parse_url(env('APP_URL'), PHP_URL_HOST) &&
            (
                !$request->session()->token()
                || $request->session()->token() !== csrf_token()
            )
            )
        {
            return response('You do not access to this api..', 403);
        }

        return $next($request);

    }
}
