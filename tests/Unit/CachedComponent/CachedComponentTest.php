<?php

use Illuminate\Support\Facades\Blade;

require_once 'tests/Unit/CachedComponent/CachedComponent.php';

beforeEach(function () {
    Cache::driver('file')->clear();
    (fn () => $this->cachers = new \SplObjectStorage)->call(app(\Backstage\PermanentCache\Laravel\PermanentCache::class));
});

test('test cached component is cached second time', function () {
    $firstRunOutput = Blade::renderComponent(new CachedComponent);
    $secondRunOutput = Blade::renderComponent(new CachedComponent);

    $this->assertEquals($firstRunOutput, $secondRunOutput);
});

test('test cached component with parameters is cached correctly', function () {
    config(['permanent-cache.components.markers.enabled' => false]);

    $randomString = str_random();

    $cachedComponentWithParameters = new CachedComponent(value: $randomString);

    $firstRunOutput = Blade::renderComponent($cachedComponentWithParameters);
    $secondRunOutput = Blade::renderComponent($cachedComponentWithParameters);

    $this->assertEquals($firstRunOutput, '<div>This is a '.$randomString.' component!</div>');
    $this->assertEquals($secondRunOutput, '<div>This is a '.$randomString.' component!</div>');
});
