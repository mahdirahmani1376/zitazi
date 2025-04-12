<?php

namespace App\Exceptions;

use Exception;

class UnProcessableResponseException extends Exception
{
    public static function causeOfTorob($message): static
    {
        return new static($message);
    }
}
