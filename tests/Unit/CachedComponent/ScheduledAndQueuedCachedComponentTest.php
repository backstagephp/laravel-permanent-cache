<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Backstage\PermanentCache\Laravel\Facades\PermanentCache;

require_once 'tests/Unit/CachedComponent/ScheduledAndQueuedCachedComponent.php';

beforeEach(function () {
    Cache::driver('file')->clear();
    Queue::fake();

    (fn () => $this->caches = new \SplObjectStorage)->call(app(\Backstage\PermanentCache\Laravel\PermanentCache::class));
});

test('test scheduled queued cached component gets scheduled', function () {
    PermanentCache::caches([
        ScheduledAndQueuedCachedComponent::class,
    ]);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($schedule) => $schedule->description === 'ScheduledAndQueuedCachedComponent');

    expect($events)->toHaveCount(1);
    expect($events->first()->expression)->toBe('* * * * *');
});

test('test scheduled queued cached component with parameters gets scheduled', function () {
    PermanentCache::caches([
        ScheduledAndQueuedCachedComponent::class => ['parameter' => 'test cached'],
    ]);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($schedule) => $schedule->description === 'ScheduledAndQueuedCachedComponent');

    expect($events)->toHaveCount(1);
    expect($events->first()->expression)->toBe('* * * * *');
});

test('test scheduled queued cached component gets executed', function () {
    PermanentCache::caches([
        ScheduledAndQueuedCachedComponent::class => ['parameter' => 'test cached'],
    ]);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($schedule) => $schedule->description === 'ScheduledAndQueuedCachedComponent');

    expect($events)->toHaveCount(1);
    expect($events->first()->expression)->toBe('* * * * *');

    PermanentCache::update();

    Queue::assertPushed(ScheduledAndQueuedCachedComponent::class);
});
