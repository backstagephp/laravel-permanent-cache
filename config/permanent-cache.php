<?php

return [
    'store' => env('PERMANENT_CACHE_STORE', 'file'),

    // Defaults for per-record model caching (Concerns\HasPermanentCache).
    // These are fall-throughs only — each model can override every value.
    'models' => [
        'chunk' => 1000,
    ],

    'components' => [
        // Add markers around the rendered value of Cached Components,
        // this helps to identify the cached value in the rendered HTML.

        // Which is useful for debugging and testing, but also for updating
        // the cached value inside another cache when using nested caches
        'markers' => [
            'enabled' => env('PERMANENT_CACHE_MARKERS_ENABLED', true),
            'hash' => env('PERMANENT_CACHE_MARKERS_HASH', env('APP_ENV') === 'production'),
        ],
    ],
];
