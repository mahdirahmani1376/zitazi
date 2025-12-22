<?php

namespace App\Actions;

use App\Models\LogModel;
use App\Models\Product;
use App\Models\Variation;

class LogManager
{
    public static function logProduct(Product $product, $message = '', $data = []): void
    {
        LogModel::create([
            'product_id' => $product->id,
            'variation_id' => null,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public static function logVariation(Variation $variation, $message = '', $data = []): void
    {
        LogModel::create([
            'product_id' => $variation->product_id,
            'variation_id' => $variation->id,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
