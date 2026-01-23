<?php

namespace Backstage\PermanentCache\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Lorisleiva\CronTranslator\CronTranslator;
use Spatie\Emoji\Emoji;
use Symfony\Component\Console\Helper\TableSeparator;
use Backstage\PermanentCache\Laravel\CachesValue;
use Backstage\PermanentCache\Laravel\Facades\PermanentCache;
use Backstage\PermanentCache\Laravel\Scheduled;

class PermanentCacheStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permanent-cache:status
                            {--P|parameters}
                            {--F|filter=}
                            {--S|sort= : The statistic to sort on, this can be one of ["size", "updated", "frequency"]}
                            {--A|ascending : Whether the sorting should be done ascending instead of descending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show status for all registered Permanent Caches';

    protected function getSize($cache): int
    {
        return strlen(serialize($cache));
    }

    protected function sortOnSize($a, $b): int
    {
        $sizeA = $this->getSize($a[0]);
        $sizeB = $this->getSize($b[0]);

        return match (true) {
            $sizeA == $sizeB => 0,
            default => $sizeA > $sizeB ? 1 : -1,
        };
    }

    protected function sortOnUpdated($a, $b): int
    {
        return $a[2]->updated_at > $b[2]->updated_at ? 1 : -1;
    }

    protected function sortOnFrequency($a, $b): int
    {
        $a = $a[0]->expression()?->getNextRunDate();
        $b = $b[0]->expression()?->getNextRunDate();

        if (is_null($a)) return is_null($b) ? 0 : -1;
        if (is_null($b)) return 1;

        return $a > $b ? 1 : -1;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sortOn = $this->option('sort');

        $items = new Collection;

        foreach (($caches = PermanentCache::configuredCaches()) as $cache) {
            $items->push([
                $cache,
                $parameters = $caches->getInfo(),
                $cache->getMeta($parameters),
            ]);
        }

        $caches = $items->when($sortOn, fn ($collection) => $collection->sort(function ($a, $b) use ($sortOn) {
            $result = match ($sortOn) {
                'size' => $this->sortOnSize($a, $b),
                'updated' => $this->sortOnUpdated($a, $b),
                'frequency' => $this->sortOnFrequency($a, $b),
                default => throw new \Exception("Invalid sorting method: \"{$sortOn}\"")
            };

            return $this->option('ascending') ? $result : -$result;
        }));

        $frequencies = collect(app(Schedule::class)->events())
            ->mapWithKeys(function ($schedule) {
                return [$schedule->description => CronTranslator::translate($schedule->expression)];
            });

        $tableRows = [];

        foreach ($caches as [$cache, $parameters, $meta]) {
            if (
                $this->option('filter') &&
                ! str_contains(strtolower($cache->getName()), strtolower($this->option('filter')))
            ) {
                continue;
            }

            $row = [
                $cache->isCached($parameters) ? Emoji::checkMarkButton() : Emoji::crossMark(),
                $cache->getName(),
                $meta ? readable_size($this->getSize($meta)) : 'N/A',
                $meta?->updated_at?->diffForHumans() ?: 'N/A',
                $frequencies[$cache->getName()] ?? 'N/A',
            ];

            if ($this->option('parameters')) {
                $row[] = $this->parseParameters($parameters);
            }

            $tableRows[] = $row;
            $tableRows[] = new TableSeparator();
        }

        array_pop($tableRows);

        $this->table(
            [null, 'Cache', 'Size', 'Last Updated', 'Frequency'] + ($this->option('parameters') ? ['Parameters'] : []),
            $tableRows,
            'box',
        );
    }

    public function parseParameters($parameters)
    {
        $queryString = http_build_query($parameters);

        return str_replace([
            '=',
            '&',
        ], [
            ': ',
            ', ',
        ], urldecode($queryString));
    }
}
