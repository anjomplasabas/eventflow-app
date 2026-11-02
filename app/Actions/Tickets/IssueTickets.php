<?php

namespace App\Actions\Tickets;

use App\Jobs\GenerateTicketQrCode;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\Ticket;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class IssueTickets
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Issue one ticket row per seat the registration owns. Returns the full
     * ticket set for the registration (existing + newly created).
     *
     * Idempotent: if tickets already exist for the registration, this does
     * nothing but still returns them.
     *
     * @return list<Ticket>
     */
    public function __invoke(Registration $registration): array
    {
        return DB::transaction(function () use ($registration): array {
            $existing = array_values(Ticket::query()
                ->where('registration_id', $registration->id)
                ->get()
                ->all());

            if (count($existing) >= $registration->quantity) {
                return $existing;
            }

            $registration->loadMissing(['event', 'ticketType', 'attendees']);
            $primaryAttendee = $registration->attendees->first();
            $attendeeName = $primaryAttendee instanceof RegistrationAttendee ? $primaryAttendee->name : 'Attendee';
            $attendeeEmail = $primaryAttendee instanceof RegistrationAttendee ? $primaryAttendee->email : '';

            $missing = max(0, $registration->quantity - count($existing));
            $created = [];
            for ($i = 0; $i < $missing; $i++) {
                $ticket = Ticket::query()->create([
                    'registration_id' => $registration->id,
                    'event_id' => $registration->event_id,
                    'ticket_type_id' => $registration->ticket_type_id,
                    'attendee_name' => $attendeeName,
                    'attendee_email' => $attendeeEmail,
                    'status' => 'issued',
                    'issued_at' => now(),
                ]);
                $created[] = $ticket;
            }

            if ($created !== []) {
                $this->audit->record('tickets.issued', $registration, after: [
                    'tickets' => array_map(fn (Ticket $t) => $t->public_token, $created),
                ]);

                $createdIds = array_map(fn (Ticket $t) => $t->id, $created);
                DB::afterCommit(function () use ($createdIds): void {
                    foreach ($createdIds as $id) {
                        GenerateTicketQrCode::dispatch($id);
                    }
                });
            }

            return [...$existing, ...$created];
        });
    }
}
