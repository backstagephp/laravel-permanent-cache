<?php

use Backstage\PermanentCache\Laravel\Facades\PermanentCache;
use Backstage\PermanentCache\Laravel\Tests\Unit\HasPermanentCache\TestModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Cache::store('file')->clear();

    Schema::dropIfExists('test_models');
    Schema::create('test_models', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    (fn () => $this->models = [])->call(app(Backstage\PermanentCache\Laravel\PermanentCache::class));
});

it('throws when caching a record without a primary key', function () {
    (new TestModel(['name' => 'unsaved']))->cached();
})->throws(LogicException::class, 'has no primary key value');

it('computes and stores the cached result on first access', function () {
    $model = TestModel::create(['name' => 'first']);

    PermanentCache::models([TestModel::class]);

    Cache::store('file')->forget("test_model:{$model->getKey()}");

    expect($model->isCached())->toBeFalse();

    $value = $model->cached();

    expect($model->isCached())->toBeTrue();
    expect($value['name'])->toBe('first');
    expect($model->cacheUpdatedAt())->not->toBeNull();
});

it('builds the cache key from the model basename and primary key', function () {
    $model = TestModel::create(['name' => 'keyed']);
    $model->writePermanentCache();

    expect(Cache::store('file')->has("test_model:{$model->getKey()}"))->toBeTrue();
});

it('refreshes the cache when a registered model is saved', function () {
    PermanentCache::models([TestModel::class]);

    $model = TestModel::create(['name' => 'saved']);

    expect($model->isCached())->toBeTrue();

    $first = $model->cached();
    usleep(1000);
    $model->update(['name' => 'updated']);

    $second = $model->fresh()->cached();

    expect($second['name'])->toBe('updated');
    expect($second['computed_at'])->toBeGreaterThan($first['computed_at']);
});

it('forgets the cache when a registered model is deleted', function () {
    PermanentCache::models([TestModel::class]);

    $model = TestModel::create(['name' => 'doomed']);
    expect($model->isCached())->toBeTrue();

    $key = "test_model:{$model->getKey()}";
    $model->delete();

    expect(Cache::store('file')->has($key))->toBeFalse();
});

it('rejects registering a class without the trait', function () {
    PermanentCache::models([Model::class]);
})->throws(InvalidArgumentException::class);

it('warms all registered records via the warm command', function () {
    PermanentCache::models([TestModel::class]);

    Cache::store('file')->clear();

    TestModel::create(['name' => 'a']);
    TestModel::create(['name' => 'b']);
    TestModel::create(['name' => 'c']);

    Cache::store('file')->clear();

    $this->artisan('permanent-cache:warm', ['--sync' => true])->assertSuccessful();

    expect(Cache::store('file')->has('test_model:1'))->toBeTrue();
    expect(Cache::store('file')->has('test_model:2'))->toBeTrue();
    expect(Cache::store('file')->has('test_model:3'))->toBeTrue();
});
