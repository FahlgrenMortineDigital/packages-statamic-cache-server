<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Jobs;

use FahlgrendigitalPackages\StatamicCacheServer\CacheServer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class UploadStaticCacheFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $absolute_cache_path)
    {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $success = Storage::disk(CacheServer::remoteDisk())
                          ->put(
                              $this->absolute_cache_path,
                              Storage::disk(CacheServer::localDisk())->get($this->absolute_cache_path)
                          );

        if (!$success) {
            $this->fail(new \Exception("Could not upload cache file to " . CacheServer::remoteDisk() . " disk: {$this->absolute_cache_path}"));
        }
    }
}