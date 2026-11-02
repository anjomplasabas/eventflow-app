<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\Event;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ArchiveEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Event $event, ?User $actor = null): Event
    {
        return DB::transaction(function () use ($event, $actor): Event {
            if (! $event->status->canTransitionTo(EventStatus::Archived)) {
                throw InvalidEventStateTransition::between($event->status, EventStatus::Archived);
            }

            $before = ['status' => $event->status->value];
            $event->status = EventStatus::Archived;
            $event->save();
            $after = ['status' => $event->status->value];

            $this->audit->record('event.archived', $event, before: $before, after: $after, actor: $actor);

            return $event;
        });
    }
}
