<?php

use Backstage\PermanentCache\Laravel\Events\PermanentCacheUpdated;
use Backstage\PermanentCache\Laravel\Events\PermanentCacheUpdating;
use Backstage\PermanentCache\Laravel\Facades\PermanentCache;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

require_once 'tests/Unit/CachedComponent/ScheduledCachedComponent.php';

beforeEach(function () {
    Cache::driver('file')->clear();

    (fn () => $this->cachers = new SplObjectStorage)->call(app(Backstage\PermanentCache\Laravel\PermanentCache::class));
});

test('test scheduled cached component gets scheduled', function () {
    PermanentCache::caches([
        ScheduledCachedComponent::class,
    ]);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($schedule) => $schedule->description === 'ScheduledCachedComponent');

    expect($events)->toHaveCount(1);
    expect($events->first()->expression)->toBe('* * * * *');
});

test('test scheduled cached component with parameters gets scheduled', function () {
    Event::fake();

    PermanentCache::caches([
        ScheduledCachedComponent::class => ['parameter' => 'test cached'],
    ]);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($schedule) => $schedule->description === 'ScheduledCachedComponent');

    expect($events)->toHaveCount(1);
    expect($events->first()->expression)->toBe('* * * * *');

    PermanentCache::update();

    Event::assertDispatchedTimes(PermanentCacheUpdating::class, times: 1);
    Event::assertDispatchedTimes(PermanentCacheUpdated::class, times : 1);
});
