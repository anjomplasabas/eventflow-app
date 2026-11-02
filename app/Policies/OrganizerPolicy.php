<?php

namespace App\Policies;

use App\Models\Organizer;
use App\Models\User;

class OrganizerPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSystemAdmin() ? true : null;
    }

    public function view(User $user, Organizer $organizer): bool
    {
        return $user->belongsToOrganizer($organizer);
    }

    public function update(User $user, Organizer $organizer): bool
    {
        return $user->ownsOrganizer($organizer);
    }

    public function manageMembers(User $user, Organizer $organizer): bool
    {
        return $user->ownsOrganizer($organizer);
    }

    public function delete(User $user, Organizer $organizer): bool
    {
        return $user->ownsOrganizer($organizer);
    }
}
