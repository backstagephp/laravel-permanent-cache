<?php

namespace Vormkracht10\PermanentCache\Commands;

use Exception;
use Illuminate\Console\Command;
use Spatie\Emoji\Emoji;
use SplObjectStorage;
use Vormkracht10\PermanentCache\Facades\PermanentCache;

use function Laravel\Prompts\progress;

class UpdatePermanentCachesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permanent-cache:update {--filter=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all registered Permanent Caches';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $configuredCaches = PermanentCache::configuredCaches();
        $caches = new SplObjectStorage;

        foreach ($configuredCaches as $c) {
            $cache = $configuredCaches->current();
            $parameters = $configuredCaches->getInfo();

            if (
                $this->option('filter') &&
                ! str_contains(strtolower($cache->getName()), strtolower($this->option('filter')))
            ) {
                continue;
            }

            $caches[$cache] = $parameters;
        }

        $progressBar = progress(label: 'Updating caches', steps: $caches->count());

        $progressBar->cancelMessage = 'Failed to update caches';

        $progressBar->start();

        foreach ($caches as $c) {
            $cache = $caches->current();

            $parameters = $caches->getInfo();

            $currentTask = $cache->getName();
            $emoji = ($progressBar->progress % 2 ? Emoji::hourglassNotDone() : Emoji::hourglassDone());

            $progressBar->hint('Updating: '.$currentTask.' '.$emoji);

            try {
                $cache->update($parameters);
            } catch (Exception $exception) {
                $progressBar->hint('Error: '.$currentTask.' '.Emoji::warning());

                sleep(2);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
    }
}
