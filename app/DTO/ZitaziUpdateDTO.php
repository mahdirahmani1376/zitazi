<?php

namespace App\DTO;

class ZitaziUpdateDTO
{
    public const OUT_OF_STOCK = 'outofstock';
    public const IN_STOCK = 'instock';
    public function __construct(
        public int $price,
        public mixed $stock_quantity,
    )
    {
    }

    public static function createFromArray(array $data): static
    {
        return new static(
            price: $data['price'],
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
}
