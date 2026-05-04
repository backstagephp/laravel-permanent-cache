<?php

namespace Backstage\PermanentCache\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshModelCacheJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Model $model)
    {
        //
    }

    public function handle(): void
    {
        if (! method_exists($this->model, 'writePermanentCache')) {
            return;
        }

        $this->model->writePermanentCache();
    }
}
