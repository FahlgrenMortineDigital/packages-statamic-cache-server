<?php

namespace FahlgrendigitalPackages\StatamicCacheServer;

class CacheServer
{
    public static function enabled(): bool
    {
        return config('cache_server.enabled');
    }

    public static function header(): string
    {
        return config('cache_server.header');
    }

    public static function triggers(): array
    {
        return config('cache_server.triggers', []);
    }

    public static function localDisk(): string
    {
        return config('cache_server.disks.local_static_files');
    }

    public static function remoteDisk(): string
    {
        return config('cache_server.disks.remote_static_files');
    }
}