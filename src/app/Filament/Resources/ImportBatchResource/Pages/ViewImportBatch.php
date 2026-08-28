<?php

namespace App\Filament\Resources\ImportBatchResource\Pages;

use App\Enums\ImportStatusEnum;
use App\Filament\Resources\ImportBatchResource\ImportBatchResource;
use App\Models\ImportBatch;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewImportBatch extends ViewRecord
{
    protected static string $resource = ImportBatchResource::class;
    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('original_filename')
                ->label('File'),

            TextEntry::make('status')
                ->badge(),

            TextEntry::make('total_rows')
                ->label('Total rows'),

            TextEntry::make('successful_rows')
                ->label('Successful'),

            TextEntry::make('failed_rows')
                ->label('Failed'),

            TextEntry::make('created_at')
                ->label('Started')
                ->dateTime(),

            TextEntry::make('finished_at')
                ->label('Finished')
                ->dateTime(),

            TextEntry::make('error')
                ->label('Import error')
                ->visible(fn($record) => filled($record->error)),
        ]);
    }

    public function importProgressUpdated(array $data): void
    {
        if ((int)$data['id'] !== $this->record->id) {
            return;
        }

        $this->record->total_rows = $data['total'];
        $this->record->successful_rows = $data['successful'];
        $this->record->failed_rows = $data['failed'];
        $this->record->status = $data['status'];

    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download report')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn(ImportBatch $record) => $record->status === ImportStatusEnum::COMPLETED
                    && filled($record->report_path)
                )
                ->url(fn(ImportBatch $record) => route('variations.import-report', $record)
                ),
        ];
    }

    protected function getListeners(): array
    {
        return [
            'echo:imports-update,.import.progress' => 'importProgressUpdated'
        ];
    }
}
