<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Notifications\EventReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendEventReminders extends Command
{
    protected $signature = 'eventflow:send-event-reminders {--hours=24 : How many hours ahead to look}';

    protected $description = 'Queue reminder emails to confirmed attendees for events starting soon.';

    public function handle(): int
    {
        $horizon = now()->addHours((int) $this->option('hours'));
        $sent = 0;

        $events = Event::query()
            ->whereIn('status', [EventStatus::Published, EventStatus::SoldOut, EventStatus::Scheduled])
            ->whereBetween('starts_at', [now(), $horizon])
            ->get();

        foreach ($events as $event) {
            Registration::query()
                ->where('event_id', $event->id)
                ->whereIn('status', [RegistrationStatus::Confirmed, RegistrationStatus::CheckedIn])
                ->whereNull('reminder_sent_at')
                ->with('attendees')
                ->chunkById(100, function ($chunk) use (&$sent): void {
                    foreach ($chunk as $registration) {
                        $attendee = $registration->attendees->first();
                        if ($attendee === null) {
                            continue;
                        }

                        Notification::route('mail', [$attendee->email => $attendee->name])
                            ->notify(new EventReminderNotification($registration));

                        $registration->reminder_sent_at = now();
                        $registration->save();
                        $sent++;
                    }
                });
        }

        $this->info("Queued {$sent} reminder email(s).");

        return self::SUCCESS;
    }
}
