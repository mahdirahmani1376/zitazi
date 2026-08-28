<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyUserOfCompletedExportJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $importBatchId,
    )
    {
    }

    public function handle(): void
    {
        $batch = ImportBatch::with('user')
            ->findOrFail($this->importBatchId);

        $failed = $batch->failed_rows;
        $successful_rows = $batch->successful_rows;

        $notification = Notification::make()
            ->title('Import completed')
            ->body(
                $failed > 0
                    ? "{$successful_rows} rows imported successfully, {$failed} rows failed."
                    : "{$successful_rows} rows imported successfully."
            )
            ->success();

        if ($failed > 0) {
            $notification->warning();
        }

        $notification
            ->actions([
                Action::make('download')
                    ->label('Download report')
                    ->url(route('variations.import-report', $batch))
                    ->button(),
            ])
            ->sendToDatabase($batch->user);
    }
}
