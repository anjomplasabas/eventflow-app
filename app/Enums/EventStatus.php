<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case SoldOut = 'sold_out';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Published, self::Cancelled, self::Archived],
            self::Scheduled => [self::Published, self::Cancelled, self::Archived],
            self::Published => [self::SoldOut, self::Completed, self::Cancelled],
            self::SoldOut => [self::Published, self::Completed, self::Cancelled],
            self::Completed => [self::Archived],
            self::Cancelled => [self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Archived;
    }
}
