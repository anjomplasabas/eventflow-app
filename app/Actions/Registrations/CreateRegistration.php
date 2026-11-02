<?php

namespace App\Actions\Registrations;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\CapacityExhausted;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\RegistrationNotAllowed;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\RegistrationConfirmedNotification;
use App\Notifications\WaitlistJoinedNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Registration\CapacityCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CreateRegistration
{
    public function __construct(
        private readonly CapacityCalculator $capacity,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, custom_answers?: array<string, mixed>|null}  $attendee
     */
    public function __invoke(
        Event $event,
        TicketType $ticketType,
        User $user,
        array $attendee,
        int $quantity = 1,
        ?string $idempotencyKey = null,
    ): Registration {
        $this->assertEventOpen($event);
        $this->assertSameEvent($event, $ticketType);

        return DB::transaction(function () use ($event, $ticketType, $user, $attendee, $quantity, $idempotencyKey) {
            if ($idempotencyKey !== null) {
                $existing = Registration::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if ($existing->ticket_type_id !== $ticketType->id || $existing->quantity !== $quantity) {
                        throw IdempotencyConflict::differentPayload($idempotencyKey);
                    }

                    return $existing;
                }
            }

            // Lock the ticket type row so concurrent requests serialize on the capacity decision.
            /** @var TicketType $lockedTicketType */
            $lockedTicketType = TicketType::query()->lockForUpdate()->findOrFail($ticketType->id);

            $this->assertTicketTypeAvailable($lockedTicketType, $quantity);

            $goingToWaitlist = false;
            if (! $this->capacity->canFit($event, $lockedTicketType, $quantity)) {
                if (! $event->waitlist_enabled) {
                    $remainingType = $this->capacity->ticketTypeRemaining($lockedTicketType);
                    if ($remainingType !== null && $remainingType < $quantity) {
                        throw CapacityExhausted::forTicketType($lockedTicketType->name);
                    }
                    throw CapacityExhausted::forEvent($event->title);
                }
                $this->assertWaitlistHasRoom($event);
                $goingToWaitlist = true;
            }

            if ($goingToWaitlist) {
                $status = RegistrationStatus::Waitlisted;
            } else {
                $status = $lockedTicketType->isFree() ? RegistrationStatus::Confirmed : RegistrationStatus::PaymentPending;
            }

            $registration = Registration::query()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $lockedTicketType->id,
                'user_id' => $user->id,
                'status' => $status,
                'quantity' => $quantity,
                'idempotency_key' => $idempotencyKey,
                'terms_accepted_at' => now(),
                'confirmed_at' => $status === RegistrationStatus::Confirmed ? now() : null,
                'waitlisted_at' => $goingToWaitlist ? now() : null,
                'waitlist_position' => $goingToWaitlist ? $this->nextWaitlistPosition($event, $lockedTicketType) : null,
            ]);

            $registration->attendees()->create([
                'name' => $attendee['name'],
                'email' => $attendee['email'],
                'phone' => $attendee['phone'] ?? null,
                'custom_answers' => $attendee['custom_answers'] ?? null,
            ]);

            $this->audit->record('registration.created', $registration, after: [
                'reference' => $registration->reference,
                'status' => $registration->status->value,
                'quantity' => $registration->quantity,
                'ticket_type_id' => $lockedTicketType->id,
                'waitlist_position' => $registration->waitlist_position,
            ], actor: $user);

            $attendeeEmail = $attendee['email'];
            $attendeeName = $attendee['name'];
            $registrationId = $registration->id;

            if ($registration->status === RegistrationStatus::Confirmed) {
                DB::afterCommit(function () use ($registrationId, $attendeeEmail, $attendeeName): void {
                    $registration = Registration::query()->with(['event.organizer', 'ticketType', 'attendees'])->findOrFail($registrationId);
                    Notification::route('mail', [$attendeeEmail => $attendeeName])
                        ->notify(new RegistrationConfirmedNotification($registration));
                });
            } elseif ($registration->status === RegistrationStatus::Waitlisted) {
                DB::afterCommit(function () use ($registrationId, $attendeeEmail, $attendeeName): void {
                    $registration = Registration::query()->with(['event.organizer', 'ticketType', 'attendees'])->findOrFail($registrationId);
                    Notification::route('mail', [$attendeeEmail => $attendeeName])
                        ->notify(new WaitlistJoinedNotification($registration));
                });
            }

            return $registration;
        });
    }

    private function assertWaitlistHasRoom(Event $event): void
    {
        if ($event->waitlist_capacity === null) {
            return;
        }

        $current = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Waitlisted)
            ->count();

        if ($current >= $event->waitlist_capacity) {
            throw CapacityExhausted::forEvent($event->title.' waitlist');
        }
    }

    private function nextWaitlistPosition(Event $event, TicketType $ticketType): int
    {
        $max = (int) Registration::query()
            ->where('event_id', $event->id)
            ->where('ticket_type_id', $ticketType->id)
            ->where('status', RegistrationStatus::Waitlisted)
            ->max('waitlist_position');

        return $max + 1;
    }

    private function assertEventOpen(Event $event): void
    {
        if (! in_array($event->status, [EventStatus::Published, EventStatus::Scheduled], strict: true)) {
            throw RegistrationNotAllowed::eventNotOpen();
        }

        $now = now();
        if ($event->registration_opens_at !== null && $now->lt($event->registration_opens_at)) {
            throw RegistrationNotAllowed::registrationWindowClosed();
        }
        if ($event->registration_closes_at !== null && $now->gt($event->registration_closes_at)) {
            throw RegistrationNotAllowed::registrationWindowClosed();
        }
    }

    private function assertTicketTypeAvailable(TicketType $ticketType, int $quantity): void
    {
        if (! $ticketType->active) {
            throw RegistrationNotAllowed::ticketTypeInactive($ticketType->name);
        }

        $now = now();
        if ($ticketType->sales_opens_at !== null && $now->lt($ticketType->sales_opens_at)) {
            throw RegistrationNotAllowed::salesWindowClosed($ticketType->name);
        }
        if ($ticketType->sales_closes_at !== null && $now->gt($ticketType->sales_closes_at)) {
            throw RegistrationNotAllowed::salesWindowClosed($ticketType->name);
        }

        if ($quantity < $ticketType->min_per_order || $quantity > $ticketType->max_per_order) {
            throw RegistrationNotAllowed::quantityOutsideBounds($ticketType->min_per_order, $ticketType->max_per_order);
        }
    }

    private function assertSameEvent(Event $event, TicketType $ticketType): void
    {
        if ($ticketType->event_id !== $event->id) {
            throw new \InvalidArgumentException('Ticket type does not belong to the given event.');
        }
    }
}
