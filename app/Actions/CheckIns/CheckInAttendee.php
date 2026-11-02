<?php

namespace App\Actions\CheckIns;

use App\Enums\CheckInMethod;
use App\Enums\RegistrationStatus;
use App\Exceptions\CheckInNotAllowed;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class CheckInAttendee
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Check an attendee in at the door via a QR scan or manual lookup. Idempotent-ish:
     * calling it twice on the same ticket throws alreadyCheckedIn — staff should see
     * the duplicate warning in the UI rather than silently allowing a second scan.
     */
    public function __invoke(
        Event $event,
        string $publicToken,
        User $staff,
        CheckInMethod $method = CheckInMethod::Qr,
    ): CheckIn {
        return DB::transaction(function () use ($event, $publicToken, $staff, $method): CheckIn {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->lockForUpdate()->where('public_token', $publicToken)->first();
            if ($ticket === null) {
                throw CheckInNotAllowed::ticketNotFound();
            }
            if ($ticket->event_id !== $event->id) {
                throw CheckInNotAllowed::wrongEvent();
            }
            if ($ticket->status !== 'issued') {
                throw CheckInNotAllowed::ticketRevoked();
            }
            if ($ticket->checked_in_at !== null) {
                throw CheckInNotAllowed::alreadyCheckedIn();
            }

            $registration = $ticket->registration()->lockForUpdate()->first();
            if ($registration === null) {
                throw CheckInNotAllowed::ticketNotFound();
            }
            if (! in_array($registration->status, [RegistrationStatus::Confirmed, RegistrationStatus::CheckedIn], strict: true)) {
                throw CheckInNotAllowed::registrationNotConfirmed($registration->status->value);
            }

            $checkIn = CheckIn::query()->create([
                'ticket_id' => $ticket->id,
                'registration_id' => $registration->id,
                'event_id' => $event->id,
                'staff_user_id' => $staff->id,
                'method' => $method,
                'scanned_at' => now(),
            ]);

            $ticket->checked_in_at = now();
            $ticket->save();

            if ($registration->status === RegistrationStatus::Confirmed) {
                $registration->status = RegistrationStatus::CheckedIn;
                $registration->checked_in_at = now();
                $registration->save();
            }

            $this->audit->record('check_in.recorded', $checkIn, after: [
                'ticket_token' => $ticket->public_token,
                'method' => $method->value,
            ], actor: $staff);

            return $checkIn;
        });
    }
}
