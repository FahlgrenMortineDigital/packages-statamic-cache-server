<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class ForAppServer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // if request is for cache server but app is not configured as cache server, 404
        if(!Route::isAppServerRequest()) {
            abort(404);
        }

        return $next($request);
    }
}