<?php

namespace Backstage\PermanentCache\Laravel\Tests\Unit\HasPermanentCache;

use Backstage\PermanentCache\Laravel\Concerns\HasPermanentCache;
use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    use HasPermanentCache;

    protected $table = 'test_models';

    protected $guarded = [];

    public $timestamps = false;

    protected $permanentCacheStore = 'file';

    public function cachedResult(): mixed
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'computed_at' => microtime(true),
        ];
    }
}
