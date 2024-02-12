<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Jobs;

use FahlgrendigitalPackages\StatamicCacheServer\Actions\TriggerAppServerStaticCacheFileDownload as TriggerAppServerStaticCacheFileDownloadAlias;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TriggerAppServerStaticCacheFileDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $cache_uri)
    {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $success = TriggerAppServerStaticCacheFileDownloadAlias::make($this->cache_uri)->handle();

        if(!$success) {
            $this->fail(new \Exception("Could not initiate app server static cache download for: {$this->cache_uri}"));
        }
    }
}