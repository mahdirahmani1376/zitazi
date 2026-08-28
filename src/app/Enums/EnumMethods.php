<?php

namespace App\Enums;

trait EnumMethods
{
    public static function getValues()
    {
        return array_map(function ($item) {
            return $item->value;
        }, self::cases());
    }
}
