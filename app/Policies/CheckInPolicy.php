<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class CheckInPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSystemAdmin() ? true : null;
    }

    /**
     * Any organizer owner or staff can perform check-in for their events.
     */
    public function performForEvent(User $user, Event $event): bool
    {
        return $user->belongsToOrganizer($event->organizer);
    }

    public function reverse(User $user, Event $event): bool
    {
        return $user->ownsOrganizer($event->organizer);
    }
}
