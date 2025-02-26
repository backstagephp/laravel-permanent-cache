<?php

namespace Backstage\PermanentCache\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see Backstage\PermanentCache\Laravel\PermanentCache
 */
class PermanentCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Backstage\PermanentCache\Laravel\PermanentCache::class;
    }
}
