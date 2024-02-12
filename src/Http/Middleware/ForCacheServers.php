<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class ForCacheServers
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(!Route::isCacheServerRequest()) {
            abort(403);
        }

        return $next($request);
    }
}