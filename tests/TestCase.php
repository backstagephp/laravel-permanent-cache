<?php

namespace Backstage\PermanentCache\Laravel\Tests;

use Backstage\PermanentCache\Laravel\PermanentCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PermanentCacheServiceProvider::class,
        ];
    }
}
