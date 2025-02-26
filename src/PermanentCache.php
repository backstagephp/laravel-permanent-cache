<?php

namespace Backstage\PermanentCache\Laravel;

use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use SplObjectStorage;

class PermanentCache
{
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
                    foreach($events as $event) {
                        Event::listen($event, fn ($e) => $cacheInstance->handle($e));
                    }
                }

                $this->caches[$cacheInstance] = $parameters;
            }
        }

        return $this;
    }

    /**
     * Update all registered permanent caches
     */
    public function update(): void
    {
        foreach ($this->caches as $cache) {
            $cache->update();
        }
    }

    public function configuredCaches(): SplObjectStorage
    {
        return $this->caches;
    }
}
