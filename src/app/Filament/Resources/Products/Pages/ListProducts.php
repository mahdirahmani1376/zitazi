<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\SyncStatusEnum;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
    public array $syncStatuses = [];
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
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
