<?php

use App\Actions\CheckIns\CheckInAttendee;
use App\Actions\CheckIns\ReverseCheckIn;
use App\Enums\CheckInMethod;
use App\Enums\OrganizerMemberRole;
use App\Enums\RegistrationStatus;
use App\Exceptions\CheckInNotAllowed;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;

function orgStaffFor(Organizer $organizer, OrganizerMemberRole $role = OrganizerMemberRole::Staff): User
{
    $user = User::factory()->create();
    $user->organizers()->attach($organizer, [
        'role' => $role->value,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    return $user;
}

function confirmedRegistrationWithTicket(Event $event): array
{
    $ticketType = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticketType)
        ->confirmed()
        ->has(RegistrationAttendee::factory()->state(['name' => 'Jane', 'email' => 'jane@x.test']), 'attendees')
        ->create(['quantity' => 1]);
    $ticket = Ticket::query()->create([
        'registration_id' => $registration->id,
        'event_id' => $event->id,
        'ticket_type_id' => $ticketType->id,
        'attendee_name' => 'Jane',
        'attendee_email' => 'jane@x.test',
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    return [$registration, $ticket];
}

it('checks in a confirmed attendee by token', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaffFor($organizer);
    $event = Event::factory()->for($organizer)->published()->create();
    [$registration, $ticket] = confirmedRegistrationWithTicket($event);

    $checkIn = app(CheckInAttendee::class)($event, $ticket->public_token, $staff);

    expect($checkIn->ticket_id)->toBe($ticket->id)
        ->and($checkIn->method)->toBe(CheckInMethod::Qr)
        ->and($ticket->fresh()->checked_in_at)->not->toBeNull()
        ->and($registration->fresh()->status)->toBe(RegistrationStatus::CheckedIn);

    expect(AuditLog::query()->where('action', 'check_in.recorded')->exists())->toBeTrue();
});

it('rejects a scan when the ticket does not exist', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaffFor($organizer);
    $event = Event::factory()->for($organizer)->published()->create();

    app(CheckInAttendee::class)($event, 'nonexistenttoken', $staff);
})->throws(CheckInNotAllowed::class);

it('rejects a scan when the ticket belongs to a different event', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaffFor($organizer);
    $eventA = Event::factory()->for($organizer)->published()->create();
    $eventB = Event::factory()->for($organizer)->published()->create();
    [, $ticket] = confirmedRegistrationWithTicket($eventB);

    expect(fn () => app(CheckInAttendee::class)($eventA, $ticket->public_token, $staff))
        ->toThrow(CheckInNotAllowed::class, 'wrong_event');
});

it('rejects a second scan on the same ticket', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaffFor($organizer);
    $event = Event::factory()->for($organizer)->published()->create();
    [, $ticket] = confirmedRegistrationWithTicket($event);

    app(CheckInAttendee::class)($event, $ticket->public_token, $staff);
    expect(fn () => app(CheckInAttendee::class)($event, $ticket->public_token, $staff))
        ->toThrow(CheckInNotAllowed::class, 'already_checked_in');
});

it('rejects a scan when the registration is not confirmed', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaffFor($organizer);
    $event = Event::factory()->for($organizer)->published()->create();
    $ticketType = TicketType::factory()->for($event)->paid()->create();
    $registration = Registration::factory()->for($event)->for($ticketType)
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create(['status' => RegistrationStatus::PaymentPending]);
    $ticket = Ticket::query()->create([
        'registration_id' => $registration->id,
        'event_id' => $event->id,
        'ticket_type_id' => $ticketType->id,
        'attendee_name' => 'X',
        'attendee_email' => 'x@x.test',
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    expect(fn () => app(CheckInAttendee::class)($event, $ticket->public_token, $staff))
        ->toThrow(CheckInNotAllowed::class, 'registration_not_confirmed');
});

it('reverses a check-in with a reason and restores registration status', function () {
    $organizer = Organizer::factory()->create();
    $owner = orgStaffFor($organizer, OrganizerMemberRole::Owner);
    $event = Event::factory()->for($organizer)->published()->create();
    [$registration, $ticket] = confirmedRegistrationWithTicket($event);
    $checkIn = app(CheckInAttendee::class)($event, $ticket->public_token, $owner);

    app(ReverseCheckIn::class)($checkIn, $owner, 'wrong person admitted');

    expect($checkIn->fresh()->reversed_at)->not->toBeNull()
        ->and($ticket->fresh()->checked_in_at)->toBeNull()
        ->and($registration->fresh()->status)->toBe(RegistrationStatus::Confirmed);

    expect(AuditLog::query()->where('action', 'check_in.reversed')->exists())->toBeTrue();
});

it('rejects reversal without a reason', function () {
    $organizer = Organizer::factory()->create();
    $owner = orgStaffFor($organizer, OrganizerMemberRole::Owner);
    $event = Event::factory()->for($organizer)->published()->create();
    [, $ticket] = confirmedRegistrationWithTicket($event);
    $checkIn = app(CheckInAttendee::class)($event, $ticket->public_token, $owner);

    app(ReverseCheckIn::class)($checkIn, $owner, '');
})->throws(DomainException::class);

it('lets staff scan via the HTTP endpoint and returns success JSON', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaffFor($organizer);
    $event = Event::factory()->for($organizer)->published()->create();
    [, $ticket] = confirmedRegistrationWithTicket($event);

    $this->actingAs($staff)
        ->postJson("/organizer/{$organizer->slug}/events/{$event->public_id}/check-in/scan", [
            'token' => $ticket->public_token,
        ])
        ->assertOk()
        ->assertJsonPath('outcome', 'success');
});

it('returns duplicate outcome JSON on a second scan via HTTP', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaffFor($organizer);
    $event = Event::factory()->for($organizer)->published()->create();
    [, $ticket] = confirmedRegistrationWithTicket($event);
    app(CheckInAttendee::class)($event, $ticket->public_token, $staff);

    $this->actingAs($staff)
        ->postJson("/organizer/{$organizer->slug}/events/{$event->public_id}/check-in/scan", [
            'token' => $ticket->public_token,
        ])
        ->assertStatus(422)
        ->assertJsonPath('outcome', 'already_checked_in');
});

it('denies attendees from scanning', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->for($organizer)->published()->create();
    [, $ticket] = confirmedRegistrationWithTicket($event);
    $attendee = User::factory()->create();

    $this->actingAs($attendee)
        ->postJson("/organizer/{$organizer->slug}/events/{$event->public_id}/check-in/scan", [
            'token' => $ticket->public_token,
        ])
        ->assertForbidden();
});

it('returns search results matching the attendee name', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaffFor($organizer);
    $event = Event::factory()->for($organizer)->published()->create();
    confirmedRegistrationWithTicket($event);

    $this->actingAs($staff)
        ->getJson("/organizer/{$organizer->slug}/events/{$event->public_id}/check-in/search?q=jane")
        ->assertOk()
        ->assertJsonPath('results.0.attendee_name', 'Jane');
});

it('refuses to let an organizer A staff check-in for organizer B events', function () {
    $orgA = Organizer::factory()->create();
    $orgB = Organizer::factory()->create();
    $staffA = orgStaffFor($orgA);
    $eventB = Event::factory()->for($orgB)->published()->create();
    [, $ticket] = confirmedRegistrationWithTicket($eventB);

    $this->actingAs($staffA)
        ->postJson("/organizer/{$orgB->slug}/events/{$eventB->public_id}/check-in/scan", [
            'token' => $ticket->public_token,
        ])
        ->assertForbidden();
});
