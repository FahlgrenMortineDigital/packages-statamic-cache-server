<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Actions;

use Illuminate\Support\Facades\Storage;

/**
 * Delete the cache files on the remote bucket
 */
class ClearRemoteStaticCacheFiles extends BaseAction
{
    public function handle(): bool
    {
        collect(Storage::disk('cache_server.disks.remote_static_files')
                      ->allFiles())
            ->each(function ($path) {
                Storage::disk('cache_server.disks.remote_static_files')->delete($path);
            });

        return true;
    }
}