<?php

namespace App\Console\Commands;

use App\Jobs\SyncVariationsJob;
use Illuminate\Console\Command;

class SyncVariationCommand extends Command
{
    protected $signature = 'app:sync-variations {--sync=true}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $sync = $this->option('sync') == true;
        SyncVariationsJob::dispatchSync($sync);
    }
}
