<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class LocalStaticCacheFileUpdateController extends Controller
{
    public function __invoke()
    {
        $cache_path = request()->get('cache-uri');
        $success    = false;

        try {
            if (Storage::disk(config('cache_server.disks.remote_static_files'))->has($cache_path)) {
                $success = Storage::disk(config('cache_server.disks.local_static_files'))
                                  ->put(
                                      $cache_path, Storage::disk(config('cache_server.disks.remote_static_files'))->get($cache_path)
                                  );
            }
        } catch (\Exception $e) {
            info($e->getMessage());
        }

        return response()->json($success ? 200 : 401);
    }
}