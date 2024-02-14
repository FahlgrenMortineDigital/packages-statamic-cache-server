<?php

namespace FahlgrendigitalPackages\StatamicCacheServer;

use FahlgrendigitalPackages\StatamicCacheServer\Console\Commands\CacheServerStaticWarm;
use FahlgrendigitalPackages\StatamicCacheServer\Http\Middleware\CacheServerStaticCacheFileTransfer;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Statamic\Statamic;

class CacheServerProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cache-server.php', 'cache-server'
        );
    }

    public function boot(): void
    {
        Route::group(
            [
                'middleware' => 'api',
                'prefix'     => 'api'
            ],
            function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cache-server.php' => config_path('cache-server.php'),
            ], ['cache-server', 'cache-server-config']);

            $this->commands([
                CacheServerStaticWarm::class
            ]);
        }

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
                && in_array(request()->header(CacheServer::header()), array_keys(CacheServer::triggers())); #in accepted values array
        });

        Route::macro('isCacheServerBuildRequest', function () {
            return CacheServer::enabled()
                && CacheServer::isBuildRequest();
        });

        if (!$this->confirmRemoteDiskIsConfigured()) {
            throw new \Exception("Cache Server: missing remote cache disk driver. Please configure a driver.");
        }

        if (!$this->confirmLocalDiskIsConfigured()) {
            throw new \Exception("Cache Server: missing local cache disk driver. Please configure a driver.");
        }
    }

    protected function confirmRemoteDiskIsConfigured(): bool
    {
        $disk = CacheServer::remoteDisk();

        return $this->app->make('config')->has("filesystems.disks.$disk");
    }

    protected function confirmLocalDiskIsConfigured(): bool
    {
        $disk = CacheServer::localDisk();

        return $this->app->make('config')->has("filesystems.disks.$disk");
    }
}