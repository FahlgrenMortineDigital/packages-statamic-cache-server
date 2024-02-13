<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Http\Controllers;

use FahlgrendigitalPackages\StatamicCacheServer\CacheServer;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class LocalStaticCacheFileUpdateController extends Controller
{
    public function __invoke()
    {
        $cache_path = request()->get('cache-uri');
        $success    = false;

        try {
            if (Storage::disk(CacheServer::remoteDisk())->has($cache_path)) {
                $success = Storage::disk(CacheServer::localDisk())
                                  ->put(
                                      $cache_path, Storage::disk(CacheServer::remoteDisk())->get($cache_path)
                                  );
            }
        } catch (\Exception $e) {
            info($e->getMessage());
        }

        return response()->json($success ? 200 : 401);
    }
}