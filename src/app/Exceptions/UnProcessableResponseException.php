<?php

namespace App\Exceptions;

use Exception;

class UnProcessableResponseException extends Exception
{
    public static function make($message, $body = null): static
    {
        return new static($message);
    }
}
