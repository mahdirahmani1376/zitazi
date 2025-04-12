<?php

namespace App\Exceptions;

use Exception;

class UnProcessableResponseException extends Exception
{
    public static function make($message): static
    {
        return new static($message);
    }
}
