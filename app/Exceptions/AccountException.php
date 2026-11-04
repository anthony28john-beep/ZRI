<?php

namespace App\Exceptions;

use RuntimeException;

class AccountException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'account', public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
