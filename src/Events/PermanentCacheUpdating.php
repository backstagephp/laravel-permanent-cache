<?php

namespace Backstage\PermanentCache\Laravel\Events;

use Backstage\PermanentCache\Laravel\Cached;
use Backstage\PermanentCache\Laravel\CachedComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class PermanentCacheUpdating
{
    use Dispatchable;

    public function __construct(public readonly Cached|CachedComponent|Model $cache)
    {
        //
    }
}
