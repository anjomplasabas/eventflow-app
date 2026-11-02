<?php

use App\Actions\Registrations\CancelRegistration;
use App\Actions\Registrations\CreateRegistration;
use App\Exceptions\CapacityExhausted;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\RegistrationCancelledNotification;
use App\Notifications\RegistrationConfirmedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

it('queues a confirmation notification after a free registration commits', function () {
    Notification::fake();

    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 10]);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);

    Notification::assertSentOnDemand(RegistrationConfirmedNotification::class);
});

it('does not send a confirmation when registration throws before commit', function () {
    Notification::fake();

    $event = Event::factory()->published()->create(['capacity' => null]);
    $ticket = TicketType::factory()->for($event)->free()->create(['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create(['quantity' => 1]);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);

    try {
        $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);
    } catch (CapacityExhausted) {
        // expected
    }

    Notification::assertNothingSent();
});

it('does not send a confirmation for paid registrations (payment_pending)', function () {
    Notification::fake();

    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->paid(2500)->create(['capacity' => 10]);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);

    Notification::assertNothingSent();
});

it('queues a cancellation notification after a registration is cancelled', function () {
    Notification::fake();

    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $user = User::factory()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)->for($user)
        ->confirmed()
        ->has(RegistrationAttendee::factory()->state(['email' => 'jane@example.com', 'name' => 'Jane']), 'attendees')
        ->create();

    /** @var CancelRegistration $action */
    $action = app(CancelRegistration::class);
    $action($registration, $user, 'changed my mind');

    Notification::assertSentOnDemand(RegistrationCancelledNotification::class);
});

it('implements ShouldQueue and lands on the notifications queue', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()->for($event)->for($ticket)->confirmed()->create();

    $notification = new RegistrationConfirmedNotification($registration);

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification->queue)->toBe('notifications');
});

it('cancellation notification also lands on the notifications queue', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()->for($event)->for($ticket)->create();

    $notification = new RegistrationCancelledNotification($registration);

    expect($notification->queue)->toBe('notifications');
});
