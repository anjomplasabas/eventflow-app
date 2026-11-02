<?php

use App\Actions\Registrations\CreateRegistration;
use App\Enums\RegistrationStatus;
use App\Exceptions\CapacityExhausted;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\WaitlistJoinedNotification;
use App\Services\Registration\CapacityCalculator;
use Illuminate\Support\Facades\Notification;

it('still throws CapacityExhausted when the waitlist is not enabled', function () {
    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => false]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create(['quantity' => 1]);

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, User::factory()->create(), ['name' => 'Jane', 'email' => 'j@x']);
})->throws(CapacityExhausted::class);

it('creates a waitlisted registration when the ticket is full and the waitlist is enabled', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create(['quantity' => 1]);

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $waitlisted = $action($event, $ticket, User::factory()->create(), ['name' => 'Jane', 'email' => 'j@x']);

    expect($waitlisted->status)->toBe(RegistrationStatus::Waitlisted)
        ->and($waitlisted->waitlist_position)->toBe(1)
        ->and($waitlisted->confirmed_at)->toBeNull()
        ->and($waitlisted->waitlisted_at)->not->toBeNull();

    Notification::assertSentOnDemand(WaitlistJoinedNotification::class);
});

it('assigns FIFO waitlist positions', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $first = $action($event, $ticket, User::factory()->create(), ['name' => 'A', 'email' => 'a@x']);
    $second = $action($event, $ticket, User::factory()->create(), ['name' => 'B', 'email' => 'b@x']);
    $third = $action($event, $ticket, User::factory()->create(), ['name' => 'C', 'email' => 'c@x']);

    expect($first->waitlist_position)->toBe(1)
        ->and($second->waitlist_position)->toBe(2)
        ->and($third->waitlist_position)->toBe(3);
});

it('enforces event waitlist_capacity', function () {
    $event = Event::factory()->published()->create([
        'capacity' => null, 'waitlist_enabled' => true, 'waitlist_capacity' => 2,
    ]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, User::factory()->create(), ['name' => 'A', 'email' => 'a@x']);
    $action($event, $ticket, User::factory()->create(), ['name' => 'B', 'email' => 'b@x']);

    expect(fn () => $action($event, $ticket, User::factory()->create(), ['name' => 'C', 'email' => 'c@x']))
        ->toThrow(CapacityExhausted::class, 'waitlist');
});

it('does not count waitlisted registrations against capacity', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => true]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, User::factory()->create(), ['name' => 'W1', 'email' => 'w1@x']);
    $action($event, $ticket, User::factory()->create(), ['name' => 'W2', 'email' => 'w2@x']);

    // Still capacity-full for new confirmed, waitlisted doesn't block anything
    $calc = app(CapacityCalculator::class);
    expect($calc->ticketTypeRemaining($ticket->fresh()))->toBe(0);
});
