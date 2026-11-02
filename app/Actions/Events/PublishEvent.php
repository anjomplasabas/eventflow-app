<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\Event;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PublishEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Event $event, ?User $actor = null): Event
    {
        return DB::transaction(function () use ($event, $actor): Event {
            if (! $event->status->canTransitionTo(EventStatus::Published)) {
                throw InvalidEventStateTransition::between($event->status, EventStatus::Published);
            }

            $before = ['status' => $event->status->value];
            $event->status = EventStatus::Published;
            $event->save();
            $after = ['status' => $event->status->value];

            $this->audit->record('event.published', $event, before: $before, after: $after, actor: $actor);

            return $event;
        });
    }
}
