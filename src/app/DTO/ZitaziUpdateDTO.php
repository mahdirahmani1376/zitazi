<?php

namespace App\DTO;

class ZitaziUpdateDTO
{
    public const OUT_OF_STOCK = 'outofstock';
    public const IN_STOCK = 'instock';
    public function __construct(
        public mixed $price,
        public mixed $stock_quantity,
    )
    {
    }

    public static function createFromArray(array $data): static
    {
        return new static(
            price: isset($data['price']) ? (int)$data['price'] : null,
            stock_quantity: $data['stock_quantity'] ?? null,
        );
    }

    public function getUpdateBody(): array
    {
        return array_filter(
            get_object_vars($this),
            fn($value) => !is_null($value)
        );
    }

    public static function getFullPayloadFromPriceAndStock(int $stock = 0, ?int $price = null): array
    {
        $data = [];
        $data['stock_status'] = $stock ? static::IN_STOCK : static::OUT_OF_STOCK;
        $data['stock_quantity'] = $stock ? 88 : 0;

        if ($price) {
            $data['sale_price'] = null;
            $data['regular_price'] = '' . $price;
            $data['price'] = $price;
        }

        return $data;
    }
}
