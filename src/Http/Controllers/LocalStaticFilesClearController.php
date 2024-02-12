<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Http\Controllers;

use FahlgrendigitalPackages\StatamicCacheServer\Actions\ClearLocalStaticCacheFiles;
use Illuminate\Routing\Controller;

/**
 * Context: Cache Servers
 */
class LocalStaticFilesClearController extends Controller
{
    public function __invoke()
    {
        $success = ClearLocalStaticCacheFiles::make()->handle();

        return response()->json($success ? 200 : 400);
    }
}