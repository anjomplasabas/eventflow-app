<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;

class TicketTypePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSystemAdmin() ? true : null;
    }

    public function viewAny(User $user, Event $event): bool
    {
        return $user->belongsToOrganizer($event->organizer);
    }

    public function createForEvent(User $user, Event $event): bool
    {
        return $user->ownsOrganizer($event->organizer);
    }

    public function update(User $user, TicketType $ticketType): bool
    {
        return $user->ownsOrganizer($ticketType->event->organizer);
    }

    public function delete(User $user, TicketType $ticketType): bool
    {
        return $user->ownsOrganizer($ticketType->event->organizer);
    }
}
