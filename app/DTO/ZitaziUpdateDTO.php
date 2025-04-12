<?php

namespace App\DTO;

class ZitaziUpdateDTO
{
    public function __construct(
        private int   $price,
        private mixed $stock_quantity,
        private mixed $stock_status,
    )
    {
    }

    public static function createFromArray(array $data): static
    {
        return new static(
            price: $data['price'],
            stock_quantity: $data['stock_quantity'] ?? null,
            stock_status: $data['stock_status'] ?? null,
        );
    }

    public function getUpdateBody(): array
    {
        $data = array_filter(
            get_object_vars($this),
            fn($value) => !is_null($value)
        );
    }
}
