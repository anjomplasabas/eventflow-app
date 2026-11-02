<?php

namespace App\Exceptions;

use DomainException;

class CapacityExhausted extends DomainException
{
    public static function forTicketType(string $ticketTypeName): self
    {
        return new self("No seats remaining for ticket type '{$ticketTypeName}'.");
    }

    public static function forEvent(string $eventTitle): self
    {
        return new self("Event '{$eventTitle}' is sold out.");
    }
}
