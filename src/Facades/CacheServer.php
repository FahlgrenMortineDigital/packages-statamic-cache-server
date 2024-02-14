<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Facades;

use Illuminate\Support\Facades\Facade;

class CacheServer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FahlgrendigitalPackages\StatamicCacheServer\CacheServer::class;
    }
}