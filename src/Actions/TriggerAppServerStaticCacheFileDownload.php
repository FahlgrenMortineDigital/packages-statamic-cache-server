<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Actions;

use Illuminate\Support\Facades\Http;

/**
 * Context: App Server
 */
class TriggerAppServerStaticCacheFileDownload extends BaseAction
{
    public function __construct(public string $cache_uri)
    {}

    public function handle(): bool
    {
        return Http::asJson()
                   ->withQueryParameters(['cache-uri' => $this->cache_uri])
                   ->get(route('cache-server.static-cache.refresh'))
                   ->successful();
    }
}