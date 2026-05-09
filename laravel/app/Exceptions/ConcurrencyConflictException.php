<?php

namespace App\Exceptions;

use Exception;

class ConcurrencyConflictException extends Exception
{
    public function __construct(string $message = 'The resource was modified by another process. Please refresh and try again.')
    {
        parent::__construct($message);
    }
}
