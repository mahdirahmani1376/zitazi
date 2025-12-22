<?php

namespace App\Actions;

use App\Models\NodeLog;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Variation;

class UpdateEthVariationAction
{
    public function __construct(
        public SeedVariationsForDecathlonAction $seedVariationsForDecathlonAction,
    )
    {
    }

    public function execute(array $data): void
    {
        foreach ($data as $result) {
            if (!$result['success']) {
                $this->logError($result);
            } else {
                $this->seedVariationsForDecathlonAction->execute($result, $result['sync'] ?? false);
            }

            NodeLog::create([
                'product_id' => $result['product_id'],
                'data' => $result,
            ]);
        }
    }

    private function logError($result): void
    {
        $product = Product::find($result['product_id']);
        foreach ($product->variations as $variation) {
            $oldStock = $variation->stock;
            $oldPrice = $variation->price;

            $variation->update([
                'status' => Variation::UNAVAILABLE,
                'stock' => 0,
            ]);

            if ($oldStock != $variation->stock) {
                $data = [
                    'old_stock' => $oldStock,
                    'new_stock' => $variation->stock,
                    'old_price' => $oldPrice,
                    'new_price' => $variation->rial_price,
                    'variation_own_id' => $variation->own_id,
                    'product_own_id' => $variation->product->own_id,
                ];

                SyncLog::create($data);
            }
        }
        LogManager::logProduct($product, 'decathlon-sync-error', [
            'result' => $result,
        ]);
    }
}
