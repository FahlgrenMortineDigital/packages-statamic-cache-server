<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Actions;

use FahlgrendigitalPackages\StatamicCacheServer\CacheServer;
use Illuminate\Support\Facades\Storage;

/**
 * Delete the cache files on the remote bucket
 */
class ClearRemoteStaticCacheFiles extends BaseAction
{
    public function handle(): bool
    {
        //delete each file instead of the whole directory in case that would be too aggressive
        collect(Storage::disk(CacheServer::remoteDisk())->allFiles())
            ->each(function ($path) {
                Storage::disk(CacheServer::remoteDisk())->delete($path);
            });

        return true;
    }
}