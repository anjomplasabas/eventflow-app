<?php

use App\Actions\Registrations\CancelRegistration;
use App\Actions\Registrations\PromoteWaitlist;
use App\Enums\RegistrationStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Support\Facades\Notification;

function waitlistedWith(Event $event, TicketType $ticket, int $position, string $email): Registration
{
    return Registration::factory()
        ->for($event)->for($ticket)
        ->waitlisted()
        ->has(RegistrationAttendee::factory()->state(['email' => $email, 'name' => $email]), 'attendees')
        ->create(['waitlist_position' => $position, 'quantity' => 1]);
}

it('promotes the next FIFO waitlisted free registration when a seat opens up', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    $taken = Registration::factory()->for($event)->for($ticket)->for(User::factory()->create())->confirmed()->create(['quantity' => 1]);
    waitlistedWith($event, $ticket, 1, 'first@x');
    waitlistedWith($event, $ticket, 2, 'second@x');

    app(CancelRegistration::class)($taken);

    $firstFresh = Registration::query()->where('ticket_type_id', $ticket->id)
        ->where('status', RegistrationStatus::Confirmed)->first();
    expect($firstFresh?->attendees()->first()?->email)->toBe('first@x')
        ->and($firstFresh?->waitlist_position)->toBeNull();

    Notification::assertSentOnDemandTimes(WaitlistPromotedNotification::class, 1);
    expect(AuditLog::query()->where('action', 'registration.promoted')->count())->toBe(1);
});

it('promotes paid waitlisted registrations to payment_pending', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->paid()->create(['capacity' => 1]);
    $taken = Registration::factory()->for($event)->for($ticket)->for(User::factory()->create())
        ->confirmed()
        ->create(['quantity' => 1]);
    waitlistedWith($event, $ticket, 1, 'buyer@x');

    app(CancelRegistration::class)($taken);

    $promoted = Registration::query()->where('ticket_type_id', $ticket->id)
        ->where('status', RegistrationStatus::PaymentPending)->firstOrFail();
    expect($promoted->waitlist_position)->toBeNull();
});

it('does nothing when there are no waitlisted entries', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    $taken = Registration::factory()->for($event)->for($ticket)->for(User::factory()->create())
        ->confirmed()->create(['quantity' => 1]);

    app(CancelRegistration::class)($taken);

    Notification::assertNothingSent();
    expect(Registration::query()->where('status', RegistrationStatus::Waitlisted)->count())->toBe(0);
});

it('respects FIFO ordering when multiple seats open', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 2]);
    $a = Registration::factory()->for($event)->for($ticket)->for(User::factory()->create())
        ->confirmed()->create(['quantity' => 1]);
    $b = Registration::factory()->for($event)->for($ticket)->for(User::factory()->create())
        ->confirmed()->create(['quantity' => 1]);
    waitlistedWith($event, $ticket, 1, 'alpha@x');
    waitlistedWith($event, $ticket, 2, 'beta@x');
    waitlistedWith($event, $ticket, 3, 'gamma@x');

    app(CancelRegistration::class)($a);
    app(CancelRegistration::class)($b);

    $confirmed = Registration::query()->where('ticket_type_id', $ticket->id)
        ->where('status', RegistrationStatus::Confirmed)->with('attendees')->get();
    $emails = $confirmed->flatMap(fn ($r) => $r->attendees->pluck('email'))->sort()->values()->all();
    expect($emails)->toBe(['alpha@x', 'beta@x']);
});

it('expire command also promotes the waitlist', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->paid()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->create([
        'status' => RegistrationStatus::PaymentPending,
        'created_at' => now()->subHour(),
        'quantity' => 1,
    ]);
    waitlistedWith($event, $ticket, 1, 'promoted@x');

    $this->artisan('eventflow:expire-pending-registrations', ['--minutes' => 30])->assertSuccessful();

    $promoted = Registration::query()->where('ticket_type_id', $ticket->id)
        ->where('status', RegistrationStatus::PaymentPending)->firstOrFail();
    expect($promoted->attendees()->first()?->email)->toBe('promoted@x');
});

it('is idempotent when the ticket is at capacity (no seat to give)', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->for(User::factory()->create())
        ->confirmed()->create(['quantity' => 1]);
    waitlistedWith($event, $ticket, 1, 'solo@x');

    app(PromoteWaitlist::class)($ticket);
    app(PromoteWaitlist::class)($ticket);

    expect(Registration::query()->where('status', RegistrationStatus::Confirmed)->count())->toBe(1);
    Notification::assertNothingSent();
});

it('does not run promotion when a waitlisted registration is cancelled', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->for(User::factory()->create())->confirmed()->create();
    $waiter = waitlistedWith($event, $ticket, 1, 'waiter@x');
    waitlistedWith($event, $ticket, 2, 'next@x');

    app(CancelRegistration::class)($waiter);

    // Nobody should have been promoted — cancelling a waitlisted entry doesn't free a seat
    expect(Registration::query()->where('status', RegistrationStatus::Confirmed)->count())->toBe(1);
    Notification::assertSentOnDemandTimes(WaitlistPromotedNotification::class, 0);
});
