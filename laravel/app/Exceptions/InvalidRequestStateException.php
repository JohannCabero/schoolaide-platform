<?php

namespace App\Exceptions;

use Exception;

class InvalidRequestStateException extends Exception
{
    public function __construct(string $message = 'The service request is not in a valid state for this operation.')
    {
        parent::__construct($message);
    }
}
