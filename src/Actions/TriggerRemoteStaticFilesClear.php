<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Actions;

use FahlgrendigitalPackages\StatamicCacheServer\Enums\CacheHeader;
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
        $header       = config('cache_server.header');
        $header_value = config('cache_server.triggers.' . CacheHeader::STATIC_CLEAR);

        return Http::asJson()
            // include header so it gets picked up by cache servers and NOT the app server
                   ->withHeaders([$header => $header_value])
                   ->get(route('static-files.clear'))->successful();
    }
}