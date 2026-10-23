<?php

namespace App\Console\Commands;

use App\Jobs\FetchTorobWithScrapeOpsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ListenForTorobCommand extends Command
{
    protected $signature = 'listen:torob {--once : Process a single message and exit}';

    protected $description = 'listens for torob scrape requests and dispatches FetchTorobWithScrapeOpsJob for each';

    public function handle(): void
    {
        $this->info('listen:torob listener command started');

        while (true) {
            try {
                $message = Redis::blpop(config('queue.TOROB_QUEUE_IN'), 0);

                if (empty($message)) {
                    continue;
                }

                $data = json_decode($message[1], true);

                $this->info('Message received for product_id: '.data_get($data, 'product.id'));

                FetchTorobWithScrapeOpsJob::dispatch($data);

                if ($this->option('once')) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::error('error in listen:torob command', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error('error happened: '.json_encode($e->getMessage()));

                if ($this->option('once')) {
                    break;
                }
            }
        }
    }
}
