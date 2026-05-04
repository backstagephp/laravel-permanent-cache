<?php

namespace Backstage\PermanentCache\Laravel\Commands;

use Backstage\PermanentCache\Laravel\Concerns\HasPermanentCache;
use Backstage\PermanentCache\Laravel\Facades\PermanentCache;
use Backstage\PermanentCache\Laravel\Jobs\RefreshModelCacheJob;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Spatie\Emoji\Emoji;
use Symfony\Component\Console\Helper\ProgressBar;

class WarmPermanentCacheCommand extends Command
{
    protected $signature = 'permanent-cache:warm
                            {--filter= : Only warm models whose class name contains this fragment}
                            {--queue : Force dispatch onto the queue regardless of model setting}
                            {--sync : Force synchronous warming regardless of model setting}
                            {--chunk=1000 : Number of records to load per chunk}';

    protected $description = 'Warm per-record permanent caches for all registered models';

    public function handle(): int
    {
        $models = collect(PermanentCache::registeredModels())
            ->filter(fn (string $class) => ! $this->option('filter')
                || str_contains(strtolower($class), strtolower($this->option('filter'))));

        if ($models->isEmpty()) {
            $this->info('No registered models match the filter.');

            return self::SUCCESS;
        }

        foreach ($models as $class) {
            $this->warmModel($class);
        }

        return self::SUCCESS;
    }

    protected function warmModel(string $class): void
    {
        if (! is_subclass_of($class, Model::class) || ! in_array(HasPermanentCache::class, class_uses_recursive($class), true)) {
            $this->warn("Skipping {$class}: not an Eloquent model using HasPermanentCache.");

            return;
        }

        $total = $class::permanentCacheableQuery()->toBase()->getCountForPagination();

        ProgressBar::setFormatDefinition('permanent-cache-warm', ' %current%/%max% [%bar%] %message%');

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat('permanent-cache-warm');
        $bar->setMessage("Warming {$class}");
        $bar->start();

        $chunk = (int) ($this->option('chunk') ?: 1000);

        $class::permanentCacheableQuery()->chunkById($chunk, function ($records) use ($bar, $class) {
            foreach ($records as $record) {
                try {
                    $this->refresh($record);
                    $emoji = ($bar->getProgress() % 2 ? Emoji::hourglassNotDone() : Emoji::hourglassDone());
                    $bar->setMessage("Warming {$class} {$emoji}");
                } catch (Exception $e) {
                    $bar->setMessage("Error: {$class}#{$record->getKey()} ".Emoji::warning());
                }

                $bar->advance();
            }
        });

        $bar->setMessage("Finished {$class}");
        $bar->finish();
        $this->newLine();
    }

    protected function refresh(Model $model): void
    {
        if ($this->option('sync')) {
            $model->writePermanentCache();

            return;
        }

        if ($this->option('queue')) {
            RefreshModelCacheJob::dispatch($model);

            return;
        }

        $model->refreshCache();
    }
}
