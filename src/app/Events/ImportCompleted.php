<?php

namespace App\Events;

use App\Models\ImportBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportCompleted implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ImportBatch $importBatch,
    )
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel(
                'imports-complete'
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->importBatch->id,
            'status' => $this->importBatch->status,
            'total' => $this->importBatch->total_rows,
            'successful' => $this->importBatch->successful_rows,
            'failed' => $this->importBatch->failed_rows,
        ];
    }
}
