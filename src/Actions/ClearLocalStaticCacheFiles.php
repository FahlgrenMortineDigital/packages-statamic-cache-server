<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Actions;

use FahlgrendigitalPackages\StatamicCacheServer\CacheServer;
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

        //change to loop through all in case parent houses more than just static files
        $parts = collect(explode('/', $destination))->filter();

        return Storage::disk(CacheServer::localDisk())
                      ->deleteDirectory($parts->last());
    }
}