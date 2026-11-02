<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

class EventPolicy
{
    public function before(?User $user, string $ability): ?bool
    {
        if ($user instanceof User && $user->isSystemAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Event $event): bool
    {
        if ($user instanceof User && $user->belongsToOrganizer($event->organizer)) {
            return true;
        }

        if ($event->trashed()) {
            return false;
        }

        if ($event->visibility !== EventVisibility::Public) {
            return false;
        }

        return in_array($event->status, [
            EventStatus::Scheduled,
            EventStatus::Published,
            EventStatus::SoldOut,
            EventStatus::Completed,
        ], strict: true);
    }

    public function createForOrganizer(User $user, Organizer $organizer): bool
    {
        return $user->ownsOrganizer($organizer);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->ownsOrganizer($event->organizer);
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->ownsOrganizer($event->organizer);
    }

    public function cancel(User $user, Event $event): bool
    {
        return $user->ownsOrganizer($event->organizer);
    }

    public function archive(User $user, Event $event): bool
    {
        return $user->ownsOrganizer($event->organizer);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->ownsOrganizer($event->organizer);
    }
}
