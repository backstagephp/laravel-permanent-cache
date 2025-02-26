# Laravel Permanent Cache

[![Total Downloads](https://img.shields.io/packagist/dt/backstage/laravel-permanent-cache.svg?style=flat-square)](https://packagist.org/packages/backstage/laravel-permanent-cache)
[![Tests](https://github.com/backstagephp/laravel-permanent-cache/actions/workflows/pest.yml/badge.svg?branch=main)](https://github.com/backstagephp/laravel-permanent-cache/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/backstagephp/laravel-permanent-cache/actions/workflows/phpstan.yml/badge.svg?branch=main)](https://github.com/backstagephp/laravel-permanent-cache/actions/workflows/phpstan.yml)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/backstagephp/laravel-permanent-cache)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/backstage/laravel-permanent-cache)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/backstage/laravel-permanent-cache.svg?style=flat-square)](https://packagist.org/packages/backstage/laravel-permanent-cache)

This package aims to provide functionality of using a *permanent* cache for tasks using heavy Eloquent models, database queries or other long running tasks in your Laravel app. The permanent cache updates itself in the background using a scheduled task or by listening to an event so no users are harmed waiting on a given request.

Its use case is primarily to be used for heavy tasks that should return complex data or HTML for the front-end that does not need to be realtime but still regularly updated. Think about external API calls that take a few seconds to process or other 1+ second tasks.

## Installation

You can install the package via composer:

```bash
composer require backstage/laravel-permanent-cache
```

Optionally, publish config files to change the default config:

```bash
php artisan vendor:publish --provider="Backstage\PermanentCache\Laravel\PermanentCacheServiceProvider"
```

The default config options:

```php
<?php

return [
    // Default cache driver to use for permanent cache
    'driver' => env('PERMANENT_CACHE_DRIVER', 'file'),

    // Option for cached components to add markers around output
    'components' => [
        // Add markers around the rendered value of Cached Components,
        // this helps to identify the cached value in the rendered HTML.

        // Which is useful for debugging and testing, but also for updating
        // the cached value inside another cache when using nested caches
        'markers' => [
            'enabled' => env('PERMANENT_CACHE_MARKERS_ENABLED', true),
            'hash' => env('PERMANENT_CACHE_MARKERS_HASH', env('APP_ENV') === 'production'),
        ],
    ],
];
```

# Usage

All caches you create must be registered to the `PermanentCache::caches` facade. 
We recommend putting this in the `boot` method of your `AppServiceProvider`.

You can register caches in multiple ways:

```php
use Backstage\PermanentCache\Laravel\Facades\PermanentCache;

# When you don't need parameters per class, you can use direct parameters or an array:

# Without an array
PermanentCache::caches(
    LongRunningTask::class,
    LongerRunningTask::class,
);

# Passing an array
$caches = [
    LongRunningTask::class,
    LongerRunningTask::class,
];

PermanentCache::caches($caches);

# Specifying parameters per class
PermanentCache::caches([
    LongRunningTask::class => ['type' => 'long'],
    LongerRunningTask::class => ['type' => 'longer'],
]);

# As a multi-dimensional array when you need to use the same class multiple times, but with different parameters
PermanentCache::caches(
    [LongRunningTask::class => ['type' => 'long']],
    [LongRunningTask::class => ['type' => 'longer']],
);
```

## Definition of a Permanent Cache

A Permanent Cache could be a task that runs longer than you'd want your users to wait for. 
That's why you need to run it in the background, updating periodically using the scheduler
or when events happen and/or using help of Laravel's queue system.

You can define the cache store and key using a `$store` property on the class, following 
the definition: `cache-driver:key`, for example: `redis:a-unique-cache-key`:

```php
use Backstage\PermanentCache\Laravel\Cached;

class LongRunningTask extends Cached
{
    protected $store = 'redis:a-unique-cache-key';

    public function run(): string
    {
        return "I'm executing a long running task!";
    }
}
```

## Caches can listen for events

If you only want to listen for a single event you can type hint the event in the `run` method:

```php
use Backstage\PermanentCache\Laravel\Cached;

class LongRunningTaskListeningForEvents extends Cached
{
    protected $store = 'redis:unique-cache-key';

    public function run(TestEvent $event): string
    {
        return "I'm executing because of {$event->name}!";
    }
}
```

### Listening for multiple events

Permanent Caches can be updated by listening to *multiple* events using an array on the `$events` property:

```php
use Backstage\PermanentCache\Laravel\Cached;

class LongRunningTaskListeningForEvents extends Cached
{
    protected $store = 'redis:unique-cache-key';

    protected $events = [
        TestEvent::class,
        OtherEvent::class,
    ];
    
    /** @param TestEvent|OtherEvent $event */
    public function run($event): string
    {
        return "I'm executing because of {$event->name}!";
    }
}
```

## Caches can be updated periodically using the scheduler

Permanent Caches can be updated using the scheduler (while also listening for events) by adding a `schedule` method or a `$expression` property with a cron string.

Note that if you decide to listen to events *and* schedule your cache, you shouldn't try to
accept an `$event` parameter in the `run` method, because when the schedule runs, this won't be given to you.

```php
use Backstage\PermanentCache\Laravel\Cached;
use Backstage\PermanentCache\Laravel\Scheduled;

class LongRunningTaskExecutedPeriodicallyOrWhenAnEventHappens extends Cached implements Scheduled
{
    protected $store = 'redis:unique-cache-key';

    protected $events = [
        TestEvent::class,
    ];

    // Use cron expression
    protected $expression = '* * * * *'; // run every minute

    public function run(): string
    {
        return "I'm executing because of {$event->name} or a scheduled run!";
    }

    // Or use the `schedule` method using a callback
    public static function schedule($callback)
    {
        return $callback->everyHour();
    }
}
```

## Caches can be updated by dispatching on the queue

Permanent Caches can be updated using a dispatch to the queue by implementing Laravel's `ShouldQueue` interface and (optionally) specifying a queue:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Backstage\PermanentCache\Laravel\Cached;
use Backstage\PermanentCache\Laravel\Scheduled;

class LongRunningTaskExecutedPeriodicallyOrWhenAnEventHappensDispatchedOnTheQueue extends Cached implements ShouldQueue
{
    protected $store = 'redis:unique-cache-key';

    public $queue = 'execute-on-this-queue';

    public function run(TestEvent $event): string
    {
        return "I'm dispatching for execution on the queue because of {$event->name}!";
    }
}
```

## Feature: Cached Blade Components

One super handy feature are "Cached Components", these are Blade Components that could contain a longer running task on which you don't want your users to wait for completing. So you execute the Blade component when needed in the background,
using the scheduler, or queue, while optionally listening for events to happen that will cause the permanent cache to update.

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Backstage\PermanentCache\Laravel\CachedComponent;
use Backstage\PermanentCache\Laravel\Scheduled;

class HeavyComponent extends CachedComponent implements Scheduled, ShouldQueue
{
    protected $store = 'redis:unique-cache-key';

    protected $events = [
        TestEvent::class,
    ];

    public function render()
    {
        return view('components.heavy-component');
    }

    public static function schedule($callback)
    {
        return $callback->everyHour();
    }
}
```

When loading your Blade component, it will always use cache instead of executing a long during task:

```blade
<x-long-during-task />
```

## Manually updating permanent caches

Manually updating a permanent cache is very simple. Just use the static `update` method. 
This will automatically run or queue the execution of the task:

```php
LongTaskInPermanentCache::update(['parameter' => 'value']);
```

Or you can update all caches at once:

```php
use Backstage\PermanentCache\Laravel\Facades\PermanentCache;

PermanentCache::update();
```

## Events when updating Permanent Caches

These events get dispatched when a Permanent Cache gets updated:

```php
# Before updating the cache
use Backstage\PermanentCache\Laravel\Events\PermanentCacheUpdating;

# After the cache has been updated
use Backstage\PermanentCache\Laravel\Events\PermanentCacheUpdated;
```

## Console commands

This package contains Artisan commands to optimize DX when implementing Permanent Cache:

```php
# Update all caches that match namespace using the optional filter
php artisan permanent-cache:update --filter=

# Show status overview of all registered caches including cached status, cache size, last updated at and scheduled update frequency
php artisan permanent-cache:status --parameters --filter=
```

##### [Read more on Jobs & Queues](https://laravel.com/docs/queues)

## Credits

-   [Mark van Eijk](https://github.com/markvaneijk)
-   [David den Haan](https://github.com/david-d-h)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.
