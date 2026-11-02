<?php

namespace App\Exceptions;

use DomainException;

class CheckInNotAllowed extends DomainException
{
    public static function ticketNotFound(): self
    {
        return new self('ticket_not_found');
    }

    public static function ticketRevoked(): self
    {
        return new self('ticket_revoked');
    }

    public static function registrationNotConfirmed(string $status): self
    {
        return new self("registration_not_confirmed:{$status}");
    }

    public static function wrongEvent(): self
    {
        return new self('wrong_event');
    }

    public static function alreadyCheckedIn(): self
    {
        return new self('already_checked_in');
    }
}
