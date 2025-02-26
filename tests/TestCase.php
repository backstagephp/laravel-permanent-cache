<?php

namespace Backstage\PermanentCache\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Backstage\PermanentCache\Laravel\PermanentCacheServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PermanentCacheServiceProvider::class,
        ];
    }
}
