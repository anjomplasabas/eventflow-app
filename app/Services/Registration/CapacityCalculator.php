<?php

namespace App\Services\Registration;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\SeatReservation;
use App\Models\TicketType;

class CapacityCalculator
{
    /**
     * Statuses that currently hold a seat.
     *
     * @return list<string>
     */
    private function occupyingStatuses(): array
    {
        return array_values(array_map(
            fn (RegistrationStatus $s) => $s->value,
            array_filter(RegistrationStatus::cases(), fn (RegistrationStatus $s) => $s->occupiesCapacity()),
        ));
    }

    public function reservedForEvent(Event $event): int
    {
        $registrationSeats = (int) Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', $this->occupyingStatuses())
            ->sum('quantity');

        $reservationSeats = (int) SeatReservation::query()
            ->where('event_id', $event->id)
            ->where('expires_at', '>', now())
            ->sum('quantity');

        return $registrationSeats + $reservationSeats;
    }

    public function reservedForTicketType(TicketType $ticketType): int
    {
        $registrationSeats = (int) Registration::query()
            ->where('ticket_type_id', $ticketType->id)
            ->whereIn('status', $this->occupyingStatuses())
            ->sum('quantity');

        $reservationSeats = (int) SeatReservation::query()
            ->where('ticket_type_id', $ticketType->id)
            ->where('expires_at', '>', now())
            ->sum('quantity');

        return $registrationSeats + $reservationSeats;
    }

    public function eventRemaining(Event $event): ?int
    {
        if ($event->capacity === null) {
            return null;
        }

        return max(0, $event->capacity - $this->reservedForEvent($event));
    }

    public function ticketTypeRemaining(TicketType $ticketType): ?int
    {
        if ($ticketType->capacity === null) {
            return null;
        }

        return max(0, $ticketType->capacity - $this->reservedForTicketType($ticketType));
    }

    /**
     * Smallest remaining among any applicable constraint. Null means unlimited.
     */
    public function effectiveRemaining(Event $event, TicketType $ticketType): ?int
    {
        $event_remaining = $this->eventRemaining($event);
        $type_remaining = $this->ticketTypeRemaining($ticketType);

        return match (true) {
            $event_remaining === null && $type_remaining === null => null,
            $event_remaining === null => $type_remaining,
            $type_remaining === null => $event_remaining,
            default => min($event_remaining, $type_remaining),
        };
    }

    public function canFit(Event $event, TicketType $ticketType, int $quantity): bool
    {
        $remaining = $this->effectiveRemaining($event, $ticketType);

        return $remaining === null || $remaining >= $quantity;
    }
}
