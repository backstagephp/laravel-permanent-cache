<?php

namespace Backstage\PermanentCache\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Backstage\PermanentCache\Laravel\Cached;
use Backstage\PermanentCache\Laravel\CachedComponent;

class PermanentCacheUpdated
{
    use Dispatchable;

    public function __construct(public readonly Cached|CachedComponent $cache, public mixed $value)
    {
        //
    }
}
