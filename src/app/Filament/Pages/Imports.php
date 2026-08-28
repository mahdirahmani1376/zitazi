<?php

namespace App\Filament\Pages;

use App\Enums\ImportStatusEnum;
use App\Imports\ImportVariationsAction;
use App\Jobs\GenerateImportReportJob;
use App\Jobs\NotifyUserOfCompletedExportJob;
use App\Models\ImportBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Imports extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationLabel = 'Import Variations';

    protected static string|null|\BackedEnum $navigationIcon =
        'heroicon-m-arrow-up-on-square';

    protected string $view = 'filament.pages.imports';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImportBatch::query()
                    ->where('user_id', auth()->id())
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(ImportStatusEnum $state) => $state->getBadgeColorForState()),

                Tables\Columns\TextColumn::make('total_rows')
                    ->label('Total'),

                Tables\Columns\TextColumn::make('successful_rows')
                    ->label('Successful')
                    ->color('success'),

                Tables\Columns\TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Finished')
                    ->dateTime(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download report')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn(ImportBatch $record) => $record->status === ImportStatusEnum::COMPLETED
                        && filled($record->report_path)
                    )
                    ->url(fn(ImportBatch $record) => route('variations.import-report', $record)
                    ),
            ], position: RecordActionsPosition::BeforeColumns);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    FileUpload::make('file')
                        ->label('Excel file')
                        ->required()
                        ->disk('local')
                        ->directory('imports/originals')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ]),
                ])
                ->action(function (array $data) {
                    $this->startImport($data['file']);
                }),
        ];
    }

    private function startImport(string $path): void
    {
        $totalRows = $this->countRows($path);

        $batch = ImportBatch::create([
            'user_id' => auth()->id(),
            'original_filename' => basename($path),
            'source_path' => $path,
            'status' => ImportStatusEnum::PENDING,
            'total_rows' => $totalRows,
        ]);

        (new ImportVariationsAction($batch->id))
            ->queue(
                Storage::disk('local')->path($path)
            )
            ->allOnQueue('import')
            ->chain([
                (new GenerateImportReportJob($batch->id))
                    ->onQueue('import'),

                (new NotifyUserOfCompletedExportJob($batch->id))
                    ->onQueue('import'),
            ]);

        Notification::make()
            ->title('Import started')
            ->body(
                'فایل برای پردازش در صف قرار گرفت. پس از اتمام نتیجه اطلاع داده می‌شود.'
            )
            ->success()
            ->sendToDatabase(auth()->user());
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return round(
            (($this->successful_rows + $this->failed_rows) / $this->total_rows) * 100,
            1
        );
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
