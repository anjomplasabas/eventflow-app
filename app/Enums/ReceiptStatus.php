<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Issued = 'issued';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Voided = 'voided';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Issued => [self::PartiallyRefunded, self::Refunded, self::Voided],
            self::PartiallyRefunded => [self::PartiallyRefunded, self::Refunded, self::Voided],
            self::Refunded, self::Voided => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Refunded, self::Voided], strict: true);
    }
}
