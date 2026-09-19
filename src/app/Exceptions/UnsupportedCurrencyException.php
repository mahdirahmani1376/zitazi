<?php

namespace App\Exceptions;

use Exception;

class UnsupportedCurrencyException extends Exception
{
    /**
     * @throws UnsupportedCurrencyException
     */
    public static function throwException(): static
    {
        throw new static('unsupported currency');
    }
}
