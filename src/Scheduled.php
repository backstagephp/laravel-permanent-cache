<?php

namespace Backstage\PermanentCache\Laravel;

use Illuminate\Console\Scheduling\CallbackEvent;

interface Scheduled
{
    /**
     * Define the schedule for this static cacher.
     *
     * @param  CallbackEvent  $callback
     * @return void
     */
    public static function schedule($callback);
}
