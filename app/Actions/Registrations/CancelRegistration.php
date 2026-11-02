<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\RegistrationCancelledNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CancelRegistration
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Registration $registration, ?User $actor = null, ?string $reason = null): Registration
    {
        return DB::transaction(function () use ($registration, $actor, $reason) {
            if (! $registration->status->canTransitionTo(RegistrationStatus::Cancelled)) {
                throw new InvalidEventStateTransition(
                    "Registration in status '{$registration->status->value}' cannot be cancelled."
                );
            }

            $before = ['status' => $registration->status->value];
            $registration->status = RegistrationStatus::Cancelled;
            $registration->cancelled_at = now();
            $registration->save();

            $this->audit->record('registration.cancelled', $registration, before: $before, after: [
                'status' => $registration->status->value,
                'reason' => $reason,
            ], actor: $actor);

            $registrationId = $registration->id;
            $ticketTypeId = $registration->ticket_type_id;
            $wasOccupying = $registration->getOriginal('status') !== RegistrationStatus::Waitlisted->value;

            DB::afterCommit(function () use ($registrationId, $reason): void {
                $registration = Registration::query()
                    ->with(['event.organizer', 'ticketType', 'attendees'])
                    ->findOrFail($registrationId);
                $attendee = $registration->attendees->first();
                if ($attendee === null) {
                    return;
                }
                Notification::route('mail', [$attendee->email => $attendee->name])
                    ->notify(new RegistrationCancelledNotification($registration, $reason));
            });

            if ($wasOccupying) {
                DB::afterCommit(function () use ($ticketTypeId): void {
                    $ticketType = TicketType::query()->find($ticketTypeId);
                    if ($ticketType !== null) {
                        app(PromoteWaitlist::class)($ticketType);
                    }
                });
            }

            return $registration;
        });
    }
}
