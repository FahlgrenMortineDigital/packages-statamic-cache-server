<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Actions;

use Illuminate\Support\Facades\Http;

/**
 * Context: App Server
 *
 * Delete the static files on the cache servers.
 */
class TriggerRemoteStaticFilesClear extends BaseAction
{

    public function handle(): bool
    {
        $cache_server_header = config('cache_server.header');

        return Http::asJson()
            // include header so it gets picked up by cache servers and NOT the app server
                   ->withHeaders([$cache_server_header => 'static-clear'])
                   ->get(route('static-files.clear'))->successful();
    }
}