<?php

namespace App\Policies;

use App\Models\Registration;
use App\Models\User;

class RegistrationPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSystemAdmin() ? true : null;
    }

    public function view(User $user, Registration $registration): bool
    {
        return $registration->user_id === $user->id;
    }

    public function cancel(User $user, Registration $registration): bool
    {
        return $registration->user_id === $user->id;
    }
}
