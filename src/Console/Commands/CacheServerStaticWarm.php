<?php

namespace FahlgrendigitalPackages\StatamicCacheServer\Console\Commands;

use FahlgrendigitalPackages\StatamicCacheServer\Actions\ClearRemoteStaticCacheFiles;
use FahlgrendigitalPackages\StatamicCacheServer\Actions\TriggerRemoteStaticFilesClear;
use FahlgrendigitalPackages\StatamicCacheServer\CacheServer;
use FahlgrendigitalPackages\StatamicCacheServer\Enums\CacheHeader;
use FahlgrendigitalPackages\StatamicCacheServer\Jobs\StaticWarm;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Statamic\Console\EnhancesCommands;
use Statamic\Console\RunsInPlease;
use Statamic\Entries\Collection as EntriesCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\URL;
use Statamic\Facades;
use Statamic\Http\Controllers\FrontendController;
use Statamic\StaticCaching\Cacher as StaticCacher;
use Statamic\Support\Str;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class CacheServerStaticWarm extends Command
{
    use EnhancesCommands;
    use RunsInPlease;

    protected $signature = 'fm:static:warm
        {--queue : Queue the requests}
        {--u|user= : HTTP authentication user}
        {--p|password= : HTTP authentication password}
        {--insecure : Skip SSL verification}
    ';

    protected $description = 'Custom FM. Warms the static cache by visiting all URLs. This should be run from the app server, not the cache servers';

    protected $shouldQueue = false;

    private $uris;

    public function handle()
    {
        if (!config('statamic.static_caching.strategy')) {
            $this->error('Static caching is not enabled.');

            return 1;
        }

        $this->shouldQueue = $this->option('queue');

        if ($this->shouldQueue && config('queue.default') === 'sync') {
            $this->error('The queue connection is set to "sync". Queueing will be disabled.');
            $this->shouldQueue = false;
        }

        $this->comment('Please wait. This may take a while if you have a lot of content.');

        $this->warm();

        $this->output->newLine();
        $this->info($this->shouldQueue
            ? 'All requests to warm the static cache have been added to the queue.'
            : 'The static cache has been warmed.'
        );

        return 0;
    }

    private function warm(): void
    {
        $cache_server_header = CacheServer::header();
        $client_options      = [
            'verify'  => $this->shouldVerifySsl(),
            'auth'    => $this->option('user') && $this->option('password')
                ? [$this->option('user'), $this->option('password')]
                : null,
            'headers' => [$cache_server_header => CacheServer::getHeader(CacheHeader::BUILD)] # Necessary for the LB to redirect this request to the cache builder
        ];
        $client              = new Client($client_options);

        $this->output->newLine();
        $this->line('Compiling URLs...');

        $requests = $this->requests();

        $this->output->newLine();

        // assumes that the caller cleared the static cache beforehand on the main server which would clear the static files
        // and the redis urls cache
        if (config('statamic.static_caching.strategy') === 'full') {
            $this->line("Clearing static files on caching servers and emptying S3 static cache bucket");

            // trigger cache server static to delete local file static cache files and s3 static cache files
            if (TriggerRemoteStaticFilesClear::make()->handle()
                && ClearRemoteStaticCacheFiles::make()->handle()) {
                $this->line("Completed!");
            } else {
                $this->error("Uh oh. Could not clear static file caches...");
            }

            $this->output->newLine();
        }

        if ($this->shouldQueue) {
            $queue = config('statamic.static_caching.warm_queue');
            $this->line(sprintf('Adding %s requests onto %squeue...', count($requests), $queue ? $queue . ' ' : ''));

            Bus::batch(collect($requests)->map(function (Request $req) use ($client_options, $queue) {
                return (new StaticWarm($req, $client_options));
            }))
               ->name('Statamic Custom FM Static Cache Warm')
               ->allowFailures()
               ->onQueue($queue)
               ->dispatch();
        } else {
            $this->line('Visiting ' . count($requests) . ' URLs...');

            $pool = new Pool($client, $requests, [
                'concurrency' => $this->concurrency(),
                'fulfilled'   => [$this, 'outputSuccessLine'],
                'rejected'    => [$this, 'outputFailureLine'],
            ]);

            $promise = $pool->promise();

            $promise->wait();
        }
    }

    private function concurrency(): int
    {
        $strategy = config('statamic.static_caching.strategy');

        return config("statamic.static_caching.strategies.$strategy.warm_concurrency", 25);
    }

    public function outputSuccessLine(Response $response, $index): void
    {
        $this->checkLine($this->getRelativeUri($index));
    }

    public function outputFailureLine($exception, $index): void
    {
        $uri = $this->getRelativeUri($index);

        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $response = $exception->getResponse();

            $message = $response->getStatusCode() . ' ' . $response->getReasonPhrase();

            if ($response->getStatusCode() == 500) {
                $message .= "\n" . Message::bodySummary($response, 500);
            }
        } else {
            $message = $exception->getMessage();
        }

        $this->crossLine("$uri → <fg=cyan>$message</fg=cyan>");
    }

    private function getRelativeUri(int $index): string
    {
        return Str::start(Str::after($this->uris()->get($index), config('app.url')), '/');
    }

    private function requests()
    {
        return $this->uris()->map(function ($uri) {
            return new Request('GET', $uri);
        })->all();
    }

    private function uris(): Collection
    {
        if ($this->uris) {
            return $this->uris;
        }

        $cacher = app(StaticCacher::class);

        return $this->uris = collect()
            ->merge($this->entryUris())
            ->merge($this->taxonomyUris())
            ->merge($this->termUris())
            ->merge($this->customRouteUris())
            ->merge($this->customRunwayUris())
            ->unique()
            ->reject(function ($uri) use ($cacher) {
                return $cacher->isExcluded($uri);
            })
            ->sort()
            ->values();
    }

    private function shouldVerifySsl(): bool
    {
        if ($this->option('insecure')) {
            return false;
        }

        return !$this->laravel->isLocal();
    }

    protected function entryUris(): Collection
    {
        $this->line('[ ] Entries...');

        $entries = Facades\Entry::all()->map(function (Entry $entry) {
            if (!$entry->published() || $entry->private()) {
                return null;
            }

            return $entry->absoluteUrl();
        })->filter();

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Entries");

        return $entries;
    }

    protected function taxonomyUris(): Collection
    {
        $this->line('[ ] Taxonomies...');

        $taxonomyUris = Facades\Taxonomy::all()
                                        ->filter(function ($taxonomy) {
                                            return view()->exists($taxonomy->template());
                                        })
                                        ->flatMap(function (Taxonomy $taxonomy) {
                                            return $taxonomy->sites()->map(function ($site) use ($taxonomy) {
                                                // Needed because Taxonomy uses the current site. If the Taxonomy
                                                // class ever gets its own localization logic we can remove this.
                                                Facades\Site::setCurrent($site);

                                                return $taxonomy->absoluteUrl();
                                            });
                                        });

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Taxonomies");

        return $taxonomyUris;
    }

    protected function termUris(): Collection
    {
        $this->line('[ ] Taxonomy terms...');

        $terms = Facades\Term::all()
                             ->merge($this->scopedTerms())
                             ->filter(function ($term) {
                                 return view()->exists($term->template());
                             })
                             ->flatMap(function (LocalizedTerm $term) {
                                 return $term->taxonomy()->sites()->map(function ($site) use ($term) {
                                     return $term->in($site)->absoluteUrl();
                                 });
                             });

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Taxonomy terms");

        return $terms;
    }

    protected function scopedTerms(): Collection
    {
        return Facades\Collection::all()
                                 ->flatMap(function (EntriesCollection $collection) {
                                     return $this->getCollectionTerms($collection);
                                 });
    }

    protected function getCollectionTerms($collection)
    {
        return $collection->taxonomies()
                          ->flatMap(function (Taxonomy $taxonomy) {
                              return $taxonomy->queryTerms()->get();
                          })
            ->map->collection($collection);
    }

    protected function customRouteUris(): Collection
    {
        $this->line('[ ] Custom routes...');

        $action = FrontendController::class . '@route';

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(function (Route $route) use ($action) {
                return $route->getActionName() === $action && !Str::contains($route->uri(), '{');
            })
            ->map(function (Route $route) {
                return URL::tidy(Str::start($route->uri(), config('app.url') . '/'));
            });

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Custom routes");

        return $routes;
    }

    protected function customRunwayUris(): Collection
    {
        $this->line('[ ] Custom runway routes...');
        $urls = [];

        foreach (config('cache-server.include.runway', []) as $include) {
            // has to be a real class and leverage runway routes to generate URIs
            if (class_exists($include)
                && in_array(\DoubleThreeDigital\Runway\Routing\Traits\RunwayRoutes::class, class_uses(new $include))) {
                $include::query()->eachById(function (Model $record) use (&$urls) {
                    $urls[] = URL::tidy(Str::start($record->uri(), config('app.url') . '/'));
                }, 10);
            }
        }

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Custom runway routes");

        return collect($urls);
    }
}