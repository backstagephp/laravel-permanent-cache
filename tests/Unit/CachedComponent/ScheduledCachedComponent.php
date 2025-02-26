<?php

use Backstage\PermanentCache\Laravel\Scheduled;

class ScheduledCachedComponent extends \Backstage\PermanentCache\Laravel\CachedComponent implements Scheduled
{
    protected $store = 'file:unique-cache-key';

    public function __construct(public string $value = '')
    {
    }

    public function render(): string
    {
        $value = $this->value;

        return <<<'HTML'
            <div>This is a {{ $value ?? 'cached' }} component!</div>
        HTML;
    }

    public static function schedule($callback)
    {
        return $callback->everyMinute();
    }
}
