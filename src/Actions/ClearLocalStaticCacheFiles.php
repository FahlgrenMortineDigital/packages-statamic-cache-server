<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Actions;

use Illuminate\Support\Facades\Storage;

/**
 * Context: App Server
 */
class ClearLocalStaticCacheFiles extends BaseAction
{

    public function handle(): bool
    {
        $destination = config('statamic.static_caching.strategies.full.path');

        if(!file_exists($destination)) {
            return false;
        }

        $parts = collect(explode('/', $destination))->filter();

        return Storage::disk(config('cache_server.disks.local_static_files'))
                      ->deleteDirectory($parts->last());
    }
}