<?php

class CachedComponent extends \Backstage\PermanentCache\Laravel\CachedComponent
{
    protected $store = 'file:unique-cache-key';

    public function __construct(public string $value = '')
    {
    }

    public function render(): string
    {
        $value = $this->value ?? str_random();

        return <<<'HTML'
            <div>This is a {{ $value ?? 'cached' }} component!</div>
        HTML;
    }
}
