<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Pending = 'pending';
    case PaymentPending = 'payment_pending';
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case CheckedIn = 'checked_in';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::PaymentPending, self::Confirmed, self::Waitlisted, self::Cancelled, self::Expired],
            self::PaymentPending => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::Refunded],
            self::Waitlisted => [self::Pending, self::PaymentPending, self::Confirmed, self::Cancelled, self::Expired],
            self::CheckedIn => [self::Refunded],
            self::Cancelled => [],
            self::Expired => [],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Expired, self::Refunded], strict: true);
    }

    public function occupiesCapacity(): bool
    {
        return in_array($this, [
            self::Pending,
            self::PaymentPending,
            self::Confirmed,
            self::CheckedIn,
        ], strict: true);
    }
}
