<?php

namespace FahlgrendigitalPackages\StatamicCacheServer;

use FahlgrendigitalPackages\StatamicCacheServer\Http\Middleware\CacheServerStaticCacheFileTransfer;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Statamic;

class CacheServerProvider extends ServiceProvider
{
    public function register(): void
    {
        $static_cache_disk = Config::get('filesystems.disks.static-cache', null);

        if(!$static_cache_disk) {
            throw new \Exception("Cache Server: missing {static-cache} disk driver. Please configure a driver.");
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        if(config('cache_server.enabled')) {
            Statamic::booted(function () {
                /** @var Router $router */
                $router = $this->app->make(Router::class);

                // This middleware handles sending static files off to S3 and then triggers the app server to download
                // those files.
                $router->pushMiddlewareToGroup('statamic.web', CacheServerStaticCacheFileTransfer::class);
            });
        }

        Route::macro('isAppServerRequest', function () {
            return !config('cache_server.enabled');
        });

        Route::macro('isCacheServerRequest', function () {
            return config('cache_server.enabled') # enabled
                && !is_null(request()->header(config('cache_server.header'))) # not empty
                && in_array(request()->header(config('cache_server.header')), config('cache_server.triggers')); #in accepted values array
        });

        Route::macro('isCacheServerBuildRequest', function () {
            return config('cache_server.enabled') && request()->header(config('cache_server.header')) === 'build';
        });
    }
}