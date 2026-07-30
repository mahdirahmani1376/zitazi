<?php

namespace App\Filament\Resources\Variations\RelationManagers;

use App\Enums\SyncStatusEnum;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ProductRelationManager extends RelationManager
{
    protected static string $relationship = 'product';
    public array $syncStatuses = [];

    protected static ?string $relatedResource = ProductResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    protected function getListeners(): array
    {
        return [
            'echo:product-sync,.product.sync.status.updated' => 'syncStatusUpdated',
        ];
    }

    public function syncStatusUpdated(array $data): void
    {
        $this->syncStatuses[$data['product_id']] = SyncStatusEnum::tryFrom($data['status']);
    }
}
