<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkEventsCompleted extends Command
{
    protected $signature = 'eventflow:mark-events-completed';

    protected $description = 'Transition events whose end time has passed to Completed.';

    public function handle(AuditLogger $audit): int
    {
        $completed = 0;

        Event::query()
            ->whereIn('status', [EventStatus::Published, EventStatus::SoldOut, EventStatus::Scheduled])
            ->where('ends_at', '<', now())
            ->chunkById(100, function ($chunk) use ($audit, &$completed): void {
                foreach ($chunk as $event) {
                    if (! $event->status->canTransitionTo(EventStatus::Completed)) {
                        continue;
                    }

                    DB::transaction(function () use ($event, $audit): void {
                        $before = ['status' => $event->status->value];
                        $event->status = EventStatus::Completed;
                        $event->save();
                        $audit->record('event.completed', $event, before: $before, after: [
                            'status' => $event->status->value,
                        ]);
                    });

                    $completed++;
                }
            });

        $this->info("Marked {$completed} event(s) completed.");

        return self::SUCCESS;
    }
}
