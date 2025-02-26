<?php

namespace Backstage\PermanentCache\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Backstage\PermanentCache\Laravel\Commands\PermanentCacheStatusCommand;
use Backstage\PermanentCache\Laravel\Commands\UpdatePermanentCacheCommand;

class PermanentCacheServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-permanent-cache')
            ->hasCommands(
                PermanentCacheStatusCommand::class,
                UpdatePermanentCacheCommand::class
            )
            ->hasConfigFile();
    }

    public function registeringPackage()
    {
        $this->app->singleton(PermanentCache::class);
    }

    public function bootingPackage()
    {
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => collect(Facades\PermanentCache::configuredCaches())
            ->filter(fn ($cacher) => is_a($cacher, Scheduled::class))
            ->each(fn ($cacher) => $cacher->schedule($schedule->job($cacher)))
        );
    }
}
