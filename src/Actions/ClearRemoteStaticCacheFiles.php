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
        collect(Storage::disk(CacheServer::remoteDisk())
                      ->allFiles())
            ->each(function ($path) {
                Storage::disk(CacheServer::remoteDisk())->delete($path);
            });

        return true;
    }
}