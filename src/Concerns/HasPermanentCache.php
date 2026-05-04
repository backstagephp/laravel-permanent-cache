<?php

namespace Backstage\PermanentCache\Laravel\Concerns;

use Backstage\PermanentCache\Laravel\Events\PermanentCacheUpdated;
use Backstage\PermanentCache\Laravel\Events\PermanentCacheUpdating;
use Backstage\PermanentCache\Laravel\Jobs\RefreshModelCacheJob;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Adds per-record permanent caching to an Eloquent model.
 *
 * @mixin Model
 *
 * Required:
 *   public function cachedResult(): mixed { ... }
 *
 * Optional:
 *   protected ?string $permanentCacheStore         // 'driver:identifier' (same shape as Cached::$store)
 *   protected ?string $permanentCacheExpression    // cron expression for scheduled re-warm
 *   protected bool    $permanentCacheQueue         // true = dispatch refresh on the queue
 *   protected ?string $permanentCacheQueueConnection
 *   protected ?string $permanentCacheQueueName
 *   public function scopePermanentCacheable($query) // restrict warm/iteration set
 */
trait HasPermanentCache
{
    public function cached(mixed $default = null): mixed
    {
        $entry = $this->getPermanentCacheEntry();

        if ($entry === null) {
            $this->refreshCache();

            $entry = $this->getPermanentCacheEntry();
        }

        return $entry?->value ?? $default;
    }

    public function refreshCache(): mixed
    {
        if ($this->shouldQueuePermanentCache()) {
            RefreshModelCacheJob::dispatch($this)
                ->onConnection($this->permanentCacheQueueConnection ?? null)
                ->onQueue($this->permanentCacheQueueName ?? null);

            return null;
        }

        return $this->writePermanentCache();
    }

    /**
     * Compute and store the cached value synchronously. Always sync, regardless of queue settings.
     * Used by the queued job and the warm command's sync mode.
     */
    public function writePermanentCache(): mixed
    {
        if (! method_exists($this, 'cachedResult')) {
            throw new \LogicException(static::class.' uses HasPermanentCache but does not implement cachedResult(): mixed.');
        }

        PermanentCacheUpdating::dispatch($this);

        $value = $this->cachedResult();

        [$store, $key] = $this->resolvePermanentCacheStore();

        Cache::store($store)->forever($key, (object) [
            'value' => $value,
            'updated_at' => now(),
        ]);

        PermanentCacheUpdated::dispatch($this, $value);

        return $value;
    }

    public function forgetCache(): bool
    {
        [$store, $key] = $this->resolvePermanentCacheStore();

        return Cache::store($store)->forget($key);
    }

    public function isCached(): bool
    {
        [$store, $key] = $this->resolvePermanentCacheStore();

        return Cache::store($store)->has($key);
    }

    public function cacheUpdatedAt(): ?Carbon
    {
        return $this->getPermanentCacheEntry()?->updated_at;
    }

    public function getPermanentCacheKey(): string
    {
        return $this->resolvePermanentCacheStore()[1];
    }

    public function getPermanentCacheStoreName(): string
    {
        return $this->resolvePermanentCacheStore()[0];
    }

    /**
     * Query builder used to iterate records during warm/update. Override the
     * `permanentCacheable` scope on the model to restrict the set.
     *
     * @return Builder<static>
     */
    public static function permanentCacheableQuery(): Builder
    {
        $query = static::query();

        if (method_exists(static::class, 'scopePermanentCacheable')) {
            $query->permanentCacheable();
        }

        return $query;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolvePermanentCacheStore(): array
    {
        $primaryKey = $this->getKey();

        if ($primaryKey === null || $primaryKey === '') {
            throw new \LogicException(
                static::class.' has no primary key value; per-record cache cannot be resolved. '
                .'Save the record before caching it.'
            );
        }

        $raw = property_exists($this, 'permanentCacheStore') ? $this->permanentCacheStore : null;

        $store = (is_string($raw) && $raw !== '')
            ? $raw
            : (config('permanent-cache.store') ?: config('cache.default'));

        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', strtolower(Str::snake(class_basename(static::class))));

        $key = $slug.':'.$primaryKey;

        return [$store, $key];
    }

    protected function getPermanentCacheEntry(): ?object
    {
        [$store, $key] = $this->resolvePermanentCacheStore();

        $entry = Cache::store($store)->get($key);

        return is_object($entry) ? $entry : null;
    }

    protected function shouldQueuePermanentCache(): bool
    {
        return property_exists($this, 'permanentCacheQueue')
            && $this->permanentCacheQueue === true;
    }

    public function getPermanentCacheExpression(): ?string
    {
        return property_exists($this, 'permanentCacheExpression') ? $this->permanentCacheExpression : null;
    }
}
