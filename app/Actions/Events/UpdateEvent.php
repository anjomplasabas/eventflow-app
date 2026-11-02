<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Event $event, array $attributes, ?User $actor = null): Event
    {
        return DB::transaction(function () use ($event, $attributes, $actor): Event {
            $before = $event->only(array_keys($attributes));
            $event->fill($attributes)->save();
            $after = $event->only(array_keys($attributes));

            $this->audit->record('event.updated', $event, before: $before, after: $after, actor: $actor);

            return $event;
        });
    }
}
