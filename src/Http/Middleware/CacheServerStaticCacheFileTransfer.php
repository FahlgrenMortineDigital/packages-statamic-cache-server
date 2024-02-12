<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Http\Middleware;

use Closure;
use FahlgrendigitalPackages\StatamicCacheServer\Jobs\TriggerAppServerStaticCacheFileDownload;
use FahlgrendigitalPackages\StatamicCacheServer\Jobs\UploadStaticCacheFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Bus;
use Statamic\Facades\File;
use Statamic\Facades\URL;
use Statamic\StaticCaching\Cacher;
use Statamic\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * Initiate transfer of static html cache file to S3
 * and subsequent download of that file to the main app presentation server
 */
class CacheServerStaticCacheFileTransfer
{

    public function __construct(private Cacher $cacher)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        # not the cache server or
        # does not have the defined cache trigger header
        # cache trigger header does not == 'build'
        # not using the full strategy
        if (!Route::isCacheServerBuildRequest()
            || config('statamic.static_caching.strategy') !== 'full') {
            return;
        }

        $lock = $this->createLock($request);

        while (!$lock->acquire()) {
            sleep(1);
        }

        if (!$this->cacher->hasCachedPage($request)) {
            return;
        }

        // returns the absolute path to the file
        $path = URL::tidy($this->cacher->getFilePath($request->getUri()));

        // strip out the absolute path and convert into a laravel flysystem filepath to use the Storage:: facade
        $cache_uri = sprintf("%s%s",
            "static",
            Str::remove(config('statamic.static_caching.strategies.full.path'), $path)
        );

        // upload the static file to s3
        // then download the file to the app server
        Bus::chain([
            new UploadStaticCacheFile($cache_uri),
            new TriggerAppServerStaticCacheFileDownload($cache_uri)
        ])
           ->onConnection('database')
           ->onQueue('default')
           ->dispatch();
    }

    private function createLock($request)
    {
        File::makeDirectory($dir = storage_path('statamic/static-caching-locks'));

        $locks = new LockFactory(new FlockStore($dir));

        $key = $this->cacher->getUrl($request);

        return $locks->createLock($key, 30);
    }
}