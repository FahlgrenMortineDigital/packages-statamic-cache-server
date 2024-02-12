<?php

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
        'remote_static_files' => env('CACHE_SERVER_LOCAL_FILES_DISK', 'static-cache'),
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
        'build',
        'static-clear'
    ],

    /*
     |--------------------------------------------------------------------------
     | Additional data to include in default static cache building requests
     |--------------------------------------------------------------------------
     */
    'include'  => [

    ]
];
