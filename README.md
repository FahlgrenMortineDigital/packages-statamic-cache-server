# Statamic Cache Server
This package allows for configuration of a Statamic application to act as a caching server for page content.

## Installation

```bash
composer require fahlgrendigital/packages-statamic-cache-server
```

```bash
php artisan vendor:publish --provider=FahlgrendigitalPackages\StatamicCacheServer\CacheServerProvider
```

### Servers
This package expects there to be a minimum of two servers: 

- Application server
- Caching server 

Both servers should have access to the same source of truth for content, images and cache stores. The details of what this 
means will be outlined later in this documentation.

### Dev Ops
The above servers also need to sit behind an [Application Load Balancer](https://aws.amazon.com/elasticloadbalancing/features/)
which functions different then a [Network Load Balancer](https://aws.amazon.com/elasticloadbalancing/features/). AWS
has the ability to easily spin up ALBs for your EC2 instances.

## Usage
This package expects two types of utilization depending on the static caching strategy configured in the Statamic
`static_caching` configuration:

- half
- full

Depending on the strategy your application is using, the caching server will function a bit differently. Those differences
will be outlined in the following sections. Before going into the strategy-specific details, we'll cover how the cache
server package works in general.

### Overview
This overview will cover the following high-level functions:

- [Kick off](#kick-off)
- [Cache building](#cache-building)
- [Cache transfer](#cache-transfer)

#### Kick Off

```bash
php artisan fm:static:warm
```

This package has a command which acts as the ignition for the content caching process. That command mimics most of the built-in
Statamic cache-warming command `php please static:warm --user={username} --password={password} --queue`. The signature 
looks like the following:

> This command accepts the same arguments as the core Statamic command.

> It is recommended to run this request from the application server rather than the cache server to save server resources
> for solely building the cache.

**Note:** This command does not clear the static cache beforehand. It is up to the caller to clear that cache, and it is highly
recommended to do this otherwise the static cache will be built off of already-cached pages.

This custom command improves on the base static:warm command by batching the page requests when the `--queue` flag is used.
It also improves on the `--queue` option by making sure to pass in the user/password params into the Guzzle request. The native
Statamic command does not do this.

When the custom command is run, it kicks off a job which essentially just visits the configured page on the site, thereby 
triggering a cache of that page. This is where the magic of the cache server kicks in.

> The batch containing all of the page requests has the following name: Statamic Custom FM Static Cache Warm

**Page Requests**

Page requests for the following content types are done by default in this command:

- Entries
- Taxonomies
- Terms
- Custom routes
- Custom runway routes

**Note:** The runway routes can be configured at cache_server.include.runway by supplying an array of Runway resource Models. It would
look something like this:

```php
'include' => [
    \App\Models\Product::class
]
```

#### Cache Building

In order for page requests to be routed to the cache server instead of your application server, your ALB must be configured
to route cache requests properly. This package allows for a customizable header to be sent with all your cache requests which 
you can use in your ALB to properly route requests.

```php
'header'   => env('CACHE_SERVER_HEADER', 'X-Cache-Trigger')
```

You may also configure the expected header values for filtering and security in your ALB. 

```php
'triggers' => [
        CacheHeader::BUILD        => env('CACHE_SERVER_HEADER_BUILD', CacheHeader::BUILD),
        CacheHeader::STATIC_CLEAR => env('CACHE_SERVER_HEADER_STATIC_CLEAR', CacheHeader::STATIC_CLEAR),
    ],
```

> The default values for build and static-clear are `build` and `static-clear`, respectively.

**Cache Response**

When the cache server detects a request for a page, it responds to the page like any other page, but at the end of the request
this package has a terminable middleware which handles additional caching logic, particularly for the `full` caching
strategy.

**Half**

Statamic includes [documentation](https://statamic.dev/static-caching#application-driver) on how this strategy works. See
their documentation for more details.

**Full**

Statamic includes [documentation](https://statamic.dev/static-caching#file-driver) on how this strategy works. See
their documentation for more details.

#### Cache Transfer
> This section only applies when using the `full` strategy as it generates flat html files which your web server will serve up.

Once a page has been visited and a cache entry & flat file have been confirmed for that request, this package fires off
two jobs in a chain which do the following in order:

- Send the flat file to the configured remote store
- Trigger the application server to download that flat file to its configured static cache file path

## Architecture
Getting the most out of this package will require some testing to see what server architecture works best for your situation.
Whatever architecture is chosen, this package has some limitations which need to be taken into consideration:

### Limitations

- If leveraging the `full` strategy, only **one caching** server is supported.
- A central, remote cache store that the application and cache servers share.
- A central, remote image store that the application and cache servers share.
- A central DB that the application and cache servers share for content (if you are leveraging a DB for content storage)

### Recommendations

**Cache**

Statamic allows for the configuration of targeted redis database connections for storing things like: application cache, 
horizon data, pulse data, and glide data. Segmenting your data will make it easier to manage.

> Recommendation: Redis

**Images**

When the caching server is building the cache, it will trigger glide image transformations and those glide images (and cached paths)
need to be accessible to the application and cache servers. That's why it is necessary to have a central store for these images.

> Recommendation: S3

### DB Setup
If your Statamic application is leveraging a DB to store content, that DB will need to be accessible by the application
and cache servers.

**Cache Queue**

The cache server leverages the database queue connection type to manage the jobs responsible for uploading and triggering static
cache file downloads after a successful page cache.

To avoid conflicting database connections between the cache and application servers, it is highly recommend to set up a specific
database connection for the cache server queue. Can be done by specifying a missing configuration within the `queue` configuration.

Add the following line to the database queue connection:

```php
'connections' => [
    'database' => [
        'connection'   => env('QUEUE_DB_CONNECTION', 'mysql'), // add this line
        'driver'       => 'database',
        'table'        => 'jobs',
        'queue'        => 'default',
        'retry_after'  => 90,
        'after_commit' => false,
    ],
]
```

The `QUEUE_DB_CONNECTION` value will need to point to a specific database connection on the **cache server only**. For example if it is called `queue-mysql`,
then in your `database` config the following section will need to be added:

```php
'connections' => [
    'queue-mysql' => [
        'driver'         => 'mysql',
        'url'            => env('DATABASE_URL'),
        'host'           => env('QUEUE_DB_HOST', '127.0.0.1'),
        'port'           => env('QUEUE_DB_PORT', '3306'),
        'database'       => env('QUEUE_DB_DATABASE', 'forge'),
        'username'       => env('QUEUE_DB_USERNAME', 'forge'),
        'password'       => env('QUEUE_DB_PASSWORD', ''),
        'unix_socket'    => env('QUEUE_DB_SOCKET', ''),
        'charset'        => 'utf8mb4',
        'collation'      => 'utf8mb4_unicode_ci',
        'prefix'         => '',
        'prefix_indexes' => true,
        'strict'         => true,
        'engine'         => null,
        'options'        => extension_loaded('pdo_mysql') ? array_filter([
            PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        ]) : [],
    ],
]
```

If your cache servers need to share a DB connection with the main application server for content, the `migrations` table 
will be shared as well. This means that the necessary `jobs` table required when using the database connection can get a 
bit squirrely. This table only needs to exist on the cache server. Depending on when and how your `php artisan migrate` is run
that `jobs` table may end up on the wrong server and in the wrong database.

Currently, we don't have an elegant solution for this other than export the `jobs` schema and importing it into the correct
queue database. SORRY!