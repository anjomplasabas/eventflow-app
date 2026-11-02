<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateEvent
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Organizer $organizer, array $attributes, ?User $actor = null): Event
    {
        return DB::transaction(function () use ($organizer, $attributes, $actor): Event {
            $event = $organizer->events()->create($attributes);
            $this->audit->record('event.created', $event, before: null, after: $event->only([
                'id', 'public_id', 'slug', 'title', 'status', 'visibility', 'starts_at', 'ends_at',
            ]), actor: $actor);

            return $event;
        });
    }
}
