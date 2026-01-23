<?php

namespace Backstage\PermanentCache\Laravel\Events;

use Backstage\PermanentCache\Laravel\Cached;
use Backstage\PermanentCache\Laravel\CachedComponent;
use Illuminate\Foundation\Events\Dispatchable;

class PermanentCacheUpdated
{
    use Dispatchable;

    public function __construct(public readonly Cached | CachedComponent $cache, public mixed $value)
    {
        //
    }
}
