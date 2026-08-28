<?php

namespace App\Filament\Resources\ImportBatchResource;

use App\Enums\ImportStatusEnum;
use App\Models\ImportBatch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;


class ImportBatchResource extends Resource
{
    protected static ?string $model = ImportBatch::class;
    protected static ?string $navigationLabel = 'Imports';
    protected static ?string $modelLabel = 'Import';

    protected static ?string $pluralModelLabel = 'Imports';

    protected static ?string $recordTitleAttribute = 'message';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowUpTray;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(ImportStatusEnum $state): string => $state->getBadgeColorForState()),

                TextColumn::make('total_rows')
                    ->label('Rows')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('successful_rows')
                    ->label('Successful')
                    ->numeric(),

                TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->numeric()
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'danger' : 'success'
                    ),

                TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('finished_at')
                    ->label('Finished')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => ImportStatusEnum::PENDING->value,
                        'processing' => ImportStatusEnum::PROCESSING->value,
                        'completed' => ImportStatusEnum::COMPLETED->value,
                        'failed' => ImportStatusEnum::FAILED->value,
                    ]),
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
                ViewAction::make(),
            ], position: RecordActionsPosition::BeforeColumns);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportBatches::route('/'),
            'view' => Pages\ViewImportBatch::route('/{record}'),
        ];
    }
}
