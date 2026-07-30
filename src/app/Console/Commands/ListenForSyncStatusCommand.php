<?php

namespace App\Console\Commands;

use App\Enums\SyncStatusEnum;
use App\Events\ProductSyncStatusChangedEvent;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ListenForSyncStatusCommand extends Command
{
    protected $signature = 'listen:sync-status';

    protected $description = 'this command listens for product sync status changes';

    public function handle()
    {
        $this->info('listen:sync-status listener command started');

        try {
            Redis::subscribe(
                ['product_sync_status_changed'],
                function ($message) {
                    $messageFormatted = is_array($message) ? json_encode($message) : $message;
                    $this->info('listen:sync-status message arrived with message: ' . $messageFormatted);

                    $data = json_decode($message, true);
                    if (!empty($data['product_id'] && !empty($data['status']))) {
                        ProductSyncStatusChangedEvent::dispatch($data['product_id'], SyncStatusEnum::tryFrom($data['status']));
                    }
                    Log::error('product sync status message corrupted', $message);
                }
            );
        } catch (Exception $e) {
            Log::error('error in listen:sync-status command', [
                'error' => $e->getMessage()
            ]);
        }
    }

}
