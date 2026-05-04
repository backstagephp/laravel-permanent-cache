<?php

namespace Backstage\PermanentCache\Laravel;

use Backstage\PermanentCache\Laravel\Commands\PermanentCacheStatusCommand;
use Backstage\PermanentCache\Laravel\Commands\UpdatePermanentCacheCommand;
use Backstage\PermanentCache\Laravel\Commands\WarmPermanentCacheCommand;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PermanentCacheServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-permanent-cache')
            ->hasCommands(
                PermanentCacheStatusCommand::class,
                UpdatePermanentCacheCommand::class,
                WarmPermanentCacheCommand::class,
            )
            ->hasConfigFile();
    }

    public function registeringPackage()
    {
        $this->app->singleton(PermanentCache::class);
    }

    public function bootingPackage()
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            collect(Facades\PermanentCache::configuredCaches())
                ->filter(fn ($cacher) => is_a($cacher, Scheduled::class))
                ->each(fn ($cacher) => $cacher->schedule($schedule->job($cacher)));

            foreach (Facades\PermanentCache::registeredModels() as $modelClass) {
                $expression = (new $modelClass)->getPermanentCacheExpression();

                if ($expression === null) {
                    continue;
                }

                $schedule->command(WarmPermanentCacheCommand::class, [
                    '--filter='.class_basename($modelClass),
                ])->cron($expression);
            }
        });
    }
}
