<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\TicketType;
use App\Notifications\WaitlistPromotedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Registration\CapacityCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PromoteWaitlist
{
    public function __construct(
        private readonly CapacityCalculator $capacity,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Promote as many waitlisted registrations as the current free capacity allows.
     *
     * Idempotent and safe to invoke from multiple triggers (cancel, expire,
     * scheduled sweep). Returns the registrations that were promoted.
     *
     * @return list<Registration>
     */
    public function __invoke(TicketType $ticketType): array
    {
        return DB::transaction(function () use ($ticketType): array {
            /** @var TicketType $locked */
            $locked = TicketType::query()->lockForUpdate()->findOrFail($ticketType->id);
            $event = $locked->event()->first();
            if ($event === null) {
                return [];
            }

            $remaining = $this->capacity->effectiveRemaining($event, $locked);
            if ($remaining !== null && $remaining <= 0) {
                return [];
            }

            $promoted = [];

            $query = Registration::query()
                ->where('ticket_type_id', $locked->id)
                ->where('status', RegistrationStatus::Waitlisted)
                ->orderBy('waitlist_position');

            foreach ($query->get() as $registration) {
                if ($remaining !== null && $registration->quantity > $remaining) {
                    continue;
                }

                $before = ['status' => $registration->status->value];
                $registration->status = $locked->isFree()
                    ? RegistrationStatus::Confirmed
                    : RegistrationStatus::PaymentPending;
                if ($registration->status === RegistrationStatus::Confirmed) {
                    $registration->confirmed_at = now();
                }
                $registration->waitlist_position = null;
                $registration->save();

                $this->audit->record('registration.promoted', $registration, before: $before, after: [
                    'status' => $registration->status->value,
                ]);

                $registrationId = $registration->id;
                $attendee = $registration->attendees()->first();
                if ($attendee !== null) {
                    $email = $attendee->email;
                    $name = $attendee->name;
                    DB::afterCommit(function () use ($registrationId, $email, $name): void {
                        $fresh = Registration::query()
                            ->with(['event.organizer', 'ticketType', 'attendees'])
                            ->findOrFail($registrationId);
                        Notification::route('mail', [$email => $name])
                            ->notify(new WaitlistPromotedNotification($fresh));
                    });
                }

                $promoted[] = $registration;
                if ($remaining !== null) {
                    $remaining -= $registration->quantity;
                    if ($remaining <= 0) {
                        break;
                    }
                }
            }

            return $promoted;
        });
    }
}
