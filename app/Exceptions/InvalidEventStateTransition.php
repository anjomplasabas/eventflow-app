<?php

namespace App\Exceptions;

use App\Enums\EventStatus;
use DomainException;

class InvalidEventStateTransition extends DomainException
{
    public static function between(EventStatus $from, EventStatus $to): self
    {
        return new self("Cannot transition event from {$from->value} to {$to->value}.");
    }
}
