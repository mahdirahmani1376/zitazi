<?php

namespace App\Console\Commands;

use App\Services\StoreHealthService;
use Illuminate\Console\Command;

class CheckStoreHealthCommand extends Command
{
    protected $signature = 'app:check-store-health';

    protected $description = 'check the zitazi and satreh stores and toggle fallback/sync state';

    public function handle(StoreHealthService $storeHealthService): int
    {
        $results = $storeHealthService->run();

        foreach ($results as $source => $result) {
            $label = StoreHealthService::LABELS[$source] ?? $source;

            if ($result['healthy']) {
                $this->components->info("{$label} is healthy (status {$result['status']})");

                continue;
            }

            $this->components->error(
                "{$label} is unreachable (status ".($result['status'] ?? 'none').'): '.$result['error']
            );
        }

        return self::SUCCESS;
    }
}
