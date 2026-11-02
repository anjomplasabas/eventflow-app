<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\Event;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class CancelEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Event $event, ?string $reason = null, ?User $actor = null): Event
    {
        return DB::transaction(function () use ($event, $reason, $actor): Event {
            if (! $event->status->canTransitionTo(EventStatus::Cancelled)) {
                throw InvalidEventStateTransition::between($event->status, EventStatus::Cancelled);
            }

            $before = ['status' => $event->status->value];
            $event->status = EventStatus::Cancelled;
            $event->save();
            $after = ['status' => $event->status->value, 'reason' => $reason];

            $this->audit->record('event.cancelled', $event, before: $before, after: $after, actor: $actor);

            return $event;
        });
    }
}
