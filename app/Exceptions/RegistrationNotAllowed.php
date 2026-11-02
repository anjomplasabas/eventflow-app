<?php

namespace App\Exceptions;

use DomainException;

class RegistrationNotAllowed extends DomainException
{
    public static function eventNotOpen(): self
    {
        return new self('Event is not currently accepting registrations.');
    }

    public static function registrationWindowClosed(): self
    {
        return new self('Registration window is closed.');
    }

    public static function ticketTypeInactive(string $name): self
    {
        return new self("Ticket type '{$name}' is not available.");
    }

    public static function salesWindowClosed(string $name): self
    {
        return new self("Sales for '{$name}' are not open.");
    }

    public static function quantityOutsideBounds(int $min, int $max): self
    {
        return new self("Quantity must be between {$min} and {$max}.");
    }
}
