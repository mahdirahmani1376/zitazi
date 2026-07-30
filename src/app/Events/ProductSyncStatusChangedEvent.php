<?php

namespace App\Events;

use App\Enums\SyncStatusEnum;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductSyncStatusChangedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int            $productId,
        public SyncStatusEnum $status,
    )
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('product-sync'),
        ];
    }

    public function viaQueue(): string
    {
        return 'events';
    }

    public function broadcastAs(): string
    {
        return 'product.sync.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->productId,
            'status' => $this->status->value,
        ];
    }
}
