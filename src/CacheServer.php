<?php

namespace FahlgrendigitalPackages\StatamicCacheServer;

use FahlgrendigitalPackages\StatamicCacheServer\Enums\CacheHeader;
use Illuminate\Http\Request;

class CacheServer
{
    public static function enabled(): bool
    {
        return config('cache-server.enabled');
    }

    public static function header(): string
    {
        return config('cache-server.header');
    }

    public static function getHeader(string $key): string
    {
        return config("cache-server.triggers.$key");
    }

    public static function triggers(): array
    {
        return config('cache-server.triggers', []);
    }

    public static function localDisk(): string
    {
        return config('cache-server.disks.local_static_files');
    }

    public static function remoteDisk(): string
    {
        return config('cache-server.disks.remote_static_files');
    }

    public static function isBuildRequest(?Request $request = null): bool
    {
        return ($request ?? request())->header(CacheServer::header()) === config('cache-server.triggers.' . CacheHeader::BUILD);
    }
}