<?php

namespace Backstage\PermanentCache\Laravel;

use Backstage\PermanentCache\Laravel\Concerns\HasPermanentCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use SplObjectStorage;

class PermanentCache
{
    /** @var array<int, class-string<Model>> */
    protected array $models = [];

    public function __construct(
        protected SplObjectStorage $caches,
        protected Application $app,
    ) {
        //
    }

    /**
     * @param  array<class-string<Cached|CachedComponent>, int>  $caches
     */
    public function caches($registeredCaches): self
    {
        $registeredCaches = func_get_args();

        if (isset($registeredCaches[0]) && ! is_array($registeredCaches[0])) {
            $registeredCaches = [$registeredCaches];
        }

        foreach ($registeredCaches as $registeredCache) {
            foreach ($registeredCache as $cache => $parameters) {
                if (is_int($cache)) {
                    if (is_string($parameters)) {
                        $cache = $parameters;
                        $parameters = [];
                    } elseif (is_string(array_key_first($parameters))) {
                        $cache = array_key_first($parameters);
                        $parameters = array_shift($parameters);
                    } else {
                        $cache = Arr::first($parameters);
                        $parameters = [];
                    }
                }

                $cacheInstance = $this->app->make($cache, $parameters);

                if ([] !== $events = $cacheInstance->getListenerEvents()) {
                    foreach ($events as $event) {
                        Event::listen($event, fn ($e) => $cacheInstance->handle($e));
                    }
                }

                $this->caches[$cacheInstance] = $parameters;
            }
        }

        return $this;
    }

    /**
     * Register Eloquent models for per-record permanent caching.
     *
     * Each registered model class must use the HasPermanentCache trait. Eloquent
     * saved/deleted (and restored, when SoftDeletes is used) listeners are attached
     * automatically so cache entries stay in sync with the underlying record.
     *
     * @param  class-string<Model>|array<int, class-string<Model>>  ...$models
     */
    public function models(...$models): self
    {
        $classes = collect($models)
            ->flatMap(fn ($entry) => is_array($entry) ? $entry : [$entry])
            ->filter()
            ->values()
            ->all();

        foreach ($classes as $class) {
            $this->registerModel($class);
        }

        return $this;
    }

    /** @return array<int, class-string<Model>> */
    public function registeredModels(): array
    {
        return $this->models;
    }

    public function configuredCaches(): SplObjectStorage
    {
        return $this->caches;
    }

    /**
     * Update all registered permanent caches AND warm all registered model caches.
     */
    public function update(): void
    {
        foreach ($this->caches as $cache) {
            $cache->update();
        }

        foreach ($this->models as $model) {
            $this->warmModel($model);
        }
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected function registerModel(string $class): void
    {
        if (! is_subclass_of($class, Model::class)) {
            throw new \InvalidArgumentException("[{$class}] is not an Eloquent model.");
        }

        if (! in_array(HasPermanentCache::class, class_uses_recursive($class), true)) {
            throw new \InvalidArgumentException(
                "[{$class}] must use the ".HasPermanentCache::class.' trait to be registered.'
            );
        }

        if (in_array($class, $this->models, true)) {
            return;
        }

        $this->models[] = $class;

        $class::saved(static fn (Model $model) => $model->refreshCache());
        $class::deleted(static fn (Model $model) => $model->forgetCache());

        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $class::restored(static fn (Model $model) => $model->refreshCache());
        }
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected function warmModel(string $class): void
    {
        $class::permanentCacheableQuery()->chunkById(1000, function ($records) {
            foreach ($records as $record) {
                $record->refreshCache();
            }
        });
    }
}
