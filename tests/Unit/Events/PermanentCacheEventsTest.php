<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Backstage\PermanentCache\Laravel\Cached;
use Backstage\PermanentCache\Laravel\Events\PermanentCacheUpdated;
use Backstage\PermanentCache\Laravel\Events\PermanentCacheUpdating;
use Backstage\PermanentCache\Laravel\Facades\PermanentCache;

require_once 'tests/Unit/Events/TestEvent.php';
require_once 'tests/Unit/Events/TestPermanentCache.php';

beforeEach(function () {
    Cache::driver('file')->clear();
    (fn () => $this->cachers = new \SplObjectStorage)->call(app(\Backstage\PermanentCache\Laravel\PermanentCache::class));
});

test('caches listeners registers when using the PermanentCache facade', function () {
    $events = Event::fake([TestEvent::class]);

    PermanentCache::caches([TestPermanentCache::class]);

    $caches = PermanentCache::configuredCaches();

    expect($caches)
        ->count()->toBe(1)
        ->current()->toBeInstanceOf(TestPermanentCache::class)
        ->and($events)->hasListeners(TestEvent::class);
});

test('cache gets updated when listening event gets fired', function () {
    global $pass;
    $pass = false;

    class T extends Cached
    {
        public function run(TestEvent $_)
        {
            global $pass;
            $pass = true;
        }
    }

    Event::fakeExcept(TestEvent::class);
    PermanentCache::caches(T::class);
    event(new TestEvent);

    expect($pass)->toBeTrue();
    unset($pass);
});

test('cache will dispatch the updating and updated events when it gets invoked', function () {
    Event::fakeExcept(TestEvent::class);
    Permanentcache::caches(TestPermanentCache::class);

    event(new TestEvent);

    Event::assertDispatchedTimes(PermanentCacheUpdating::class, times: 1);
    Event::assertDispatchedTimes(PermanentCacheUpdated::class, times : 1);
});
