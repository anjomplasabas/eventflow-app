<?php

namespace App\Exceptions;

use DomainException;

class IdempotencyConflict extends DomainException
{
    public static function differentPayload(string $key): self
    {
        return new self("Idempotency key '{$key}' has already been used with a different payload.");
    }
}
