<?php

namespace App\Filament\Resources\ImportBatchResource\Pages;

use App\Enums\ImportStatusEnum;
use App\Filament\Resources\ImportBatchResource\ImportBatchResource;
use App\Imports\ImportVariationsAction;
use App\Jobs\GenerateImportReportJob;
use App\Jobs\NotifyUserOfCompletedExportJob;
use App\Models\ImportBatch;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListImportBatches extends ListRecords
{
    protected static string $resource = ImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload')
                ->label('Upload Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('Excel file')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->disk('local')
                        ->directory('imports/uploads')
                        ->preserveFilenames(),
                ])
                ->action(function (array $data): void {
                    $this->startImport($data['file']);
                }),
        ];
    }

    private function startImport(string $path): void
    {
        $absolutePath = Storage::disk('local')->path($path);

        $totalRows = $this->countRows($path);

        $batch = ImportBatch::create([
            'user_id' => auth()->id(),
            'original_filename' => basename($path),
            'source_path' => $path,
            'status' => ImportStatusEnum::PENDING,
            'total_rows' => $totalRows
        ]);

        (new ImportVariationsAction($batch->id))
            ->queue($absolutePath)
            ->allOnQueue('import')
            ->chain([
                (new GenerateImportReportJob($batch->id))
                    ->onQueue('import'),

                (new NotifyUserOfCompletedExportJob($batch->id))
                    ->onQueue('import'),
            ]);

        Notification::make()
            ->title('Import started')
            ->body('The Excel file has been queued for processing.')
            ->success()
            ->send();

        $this->refresh();
    }

    protected function getListeners(): array
    {
        return [
            'echo:imports-update,.import.progress' => 'syncImportState',
        ];
    }

    public function syncImportState(array $data): void
    {
        $this->resetTable();
    }

    private function countRows(string $path): int
    {
        $absolutePath = Storage::disk('local')->path($path);

        $spreadsheet = IOFactory::load($absolutePath);

        $sheet = $spreadsheet->getActiveSheet();

        return max(
            0,
            $sheet->getHighestRow() - 1
        );
    }
}
