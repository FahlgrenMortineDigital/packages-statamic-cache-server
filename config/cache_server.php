<?php

use FahlgrendigitalPackages\StatamicCacheServer\Enums\CacheHeader;

return [
    /*
    |--------------------------------------------------------------------------
    | Configure this application as a cache building server
    |--------------------------------------------------------------------------
    */
    'enabled'  => env('CACHE_SERVER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Configure this application as a cache building server
    |--------------------------------------------------------------------------
    */
    'disks'    => [
        'local_static_files'  => env('CACHE_SERVER_LOCAL_FILES_DISK', 'public'),
        'remote_static_files' => env('CACHE_SERVER_REMOTE_FILES_DISK', 'static-cache'),
    ],

    /*
     |--------------------------------------------------------------------------
     | Configure the HTTP header to segment requests destined for the cache server
     |--------------------------------------------------------------------------
     */
    'header'   => env('CACHE_SERVER_HEADER', 'X-Cache-Trigger'),

    /*
     |--------------------------------------------------------------------------
     | Allowed header values
     |--------------------------------------------------------------------------
     */
    'triggers' => [
        CacheHeader::BUILD        => env('CACHE_SERVER_HEADER_BUILD', CacheHeader::BUILD),
        CacheHeader::STATIC_CLEAR => env('CACHE_SERVER_HEADER_STATIC_CLEAR', CacheHeader::STATIC_CLEAR),
    ],

    /*
     |--------------------------------------------------------------------------
     | Additional data to include in default static cache building requests
     |--------------------------------------------------------------------------
     */
    'include'  => [

    ],
];
