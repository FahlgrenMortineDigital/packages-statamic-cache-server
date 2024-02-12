<?php

use FahlgrendigitalPackages\StatamicCacheServer\Http\Controllers;
use FahlgrendigitalPackages\StatamicCacheServer\Http\Middleware;
use Illuminate\Support\Facades\Route;

Route::prefix('cache-server')->group(function () {
    // download remote static cache file to app server
    Route::get('/static-cache-refresh', Controllers\LocalStaticCacheFileUpdateController::class)
         ->middleware([
             Middleware\ForAppServer::class
         ])->name('cache-server.static-cache.refresh');

// clear local static cache files on cache server
    Route::get('/static-files-clear', Controllers\LocalStaticFilesClearController::class)
         ->middleware([
             Middleware\ForCacheServers::class
         ])->name('cache-server.static-files.clear');
});
