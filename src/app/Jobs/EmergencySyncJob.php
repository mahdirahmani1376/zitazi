<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class EmergencySyncJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $source = null,
        public bool  $invalid = false,
        public array $ids = []
    )
    {
    }

    public function onQueue($queue)
    {
        return 'emergency';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Artisan::call('emergency:product-update', [
            '--source' => $this->source,
            '--invalid' => $this->invalid,
            '--id' => $this->ids
        ]);
    }
}
