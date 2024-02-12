<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Jobs;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StaticWarm implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct(public Request $request, public array $client_options)
    {
    }

    public function handle()
    {
        if ($this->batch()->cancelled()) {
            // Determine if the batch has been cancelled...

            return;
        }

        $client = new Client($this->client_options);

        $client->send($this->request);
    }
}