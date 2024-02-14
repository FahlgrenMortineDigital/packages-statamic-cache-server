<?php

namespace FahlgrendigitalPackages\StatamicCacheServer;

use FahlgrendigitalPackages\StatamicCacheServer\Enums\CacheHeader;
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
        $disk              = CacheServer::remoteDisk();
        $static_cache_disk = Config::get("filesystems.disks.$disk", null);

        if (!$static_cache_disk) {
            throw new \Exception("Cache Server: missing {$disk} disk driver. Please configure a driver.");
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        $this->publishes([
            __DIR__.'/../config/cache_server.php' => config_path('cache_server.php'),
        ], 'cache-server-config');

        if (CacheServer::enabled()) {
            Statamic::booted(function () {
                /** @var Router $router */
                $router = $this->app->make(Router::class);

                // This middleware handles sending static files off to S3 and then triggers the app server to download
                // those files.
                $router->pushMiddlewareToGroup('statamic.web', CacheServerStaticCacheFileTransfer::class);
            });
        }

        Route::macro('isAppServerRequest', function () {
            return !CacheServer::enabled();
        });

        Route::macro('isCacheServerRequest', function () {
            return CacheServer::enabled() # enabled
                && !is_null(request()->header(CacheServer::header())) # not empty
                && in_array(request()->header(CacheServer::header()), CacheServer::triggers()); #in accepted values array
        });

        Route::macro('isCacheServerBuildRequest', function () {
            return CacheServer::enabled()
                && request()->header(CacheServer::header()) === config('cache_server.triggers.' . CacheHeader::BUILD);
        });
    }
}