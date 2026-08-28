<?php

namespace App\Events;

use App\Models\ImportBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportProgressUpdated implements ShouldBroadcastNow
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
                'imports-update'
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.progress';
    }

    public function broadcastWith(): array
    {
        $processed =
            $this->importBatch->successful_rows
            + $this->importBatch->failed_rows;

        $percentage = $this->importBatch->total_rows > 0
            ? round(
                ($processed / $this->importBatch->total_rows) * 100,
                1
            )
            : 0;

        return [
            'id' => $this->importBatch->id,

            'status' => $this->importBatch->status,

            'total' => $this->importBatch->total_rows,

            'successful' =>
                $this->importBatch->successful_rows,

            'failed' =>
                $this->importBatch->failed_rows,

            'processed' => $processed,

            'percentage' => $percentage,
        ];
    }
}
