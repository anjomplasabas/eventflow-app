<?php

use App\Actions\Registrations\CreateRegistration;
use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\CapacityExhausted;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\RegistrationNotAllowed;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;

function freeTicket(Event $event, array $overrides = []): TicketType
{
    return TicketType::factory()->for($event)->free()->create(array_merge([
        'name' => 'General',
        'capacity' => 10,
        'min_per_order' => 1,
        'max_per_order' => 5,
    ], $overrides));
}

function paidTicket(Event $event, array $overrides = []): TicketType
{
    return TicketType::factory()->for($event)->paid()->create(array_merge([
        'name' => 'VIP',
        'capacity' => 10,
        'min_per_order' => 1,
        'max_per_order' => 5,
    ], $overrides));
}

it('creates a confirmed registration for a free ticket', function () {
    $event = Event::factory()->published()->create(['capacity' => 50]);
    $ticket = freeTicket($event);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $registration = $action($event, $ticket, $user, [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    expect($registration->status)->toBe(RegistrationStatus::Confirmed)
        ->and($registration->confirmed_at)->not->toBeNull()
        ->and($registration->attendees)->toHaveCount(1)
        ->and($registration->reference)->toStartWith('REG-');

    expect(AuditLog::query()->where('action', 'registration.created')->exists())->toBeTrue();
});

it('leaves a paid registration in payment_pending state', function () {
    $event = Event::factory()->published()->create();
    $ticket = paidTicket($event);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $registration = $action($event, $ticket, $user, [
        'name' => 'Jane',
        'email' => 'jane@example.com',
    ]);

    expect($registration->status)->toBe(RegistrationStatus::PaymentPending)
        ->and($registration->confirmed_at)->toBeNull();
});

it('rejects registration when the event is not published', function () {
    $event = Event::factory()->draft()->create();
    $ticket = freeTicket($event);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);
})->throws(RegistrationNotAllowed::class, 'not currently accepting');

it('rejects registration before the registration window opens', function () {
    $event = Event::factory()->published()->create([
        'registration_opens_at' => now()->addDay(),
        'registration_closes_at' => now()->addDays(10),
    ]);
    $ticket = freeTicket($event);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);
})->throws(RegistrationNotAllowed::class, 'window is closed');

it('rejects inactive ticket types', function () {
    $event = Event::factory()->published()->create();
    $ticket = freeTicket($event, ['active' => false]);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);
})->throws(RegistrationNotAllowed::class, 'not available');

it('rejects quantities outside min/max', function () {
    $event = Event::factory()->published()->create();
    $ticket = freeTicket($event, ['min_per_order' => 2, 'max_per_order' => 4]);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com'], quantity: 1);
})->throws(RegistrationNotAllowed::class, 'Quantity must be between');

it('throws CapacityExhausted when the ticket type is full', function () {
    $event = Event::factory()->published()->create(['capacity' => 50]);
    $ticket = freeTicket($event, ['capacity' => 2]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create(['quantity' => 2]);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);
})->throws(CapacityExhausted::class, "ticket type 'General'");

it('throws CapacityExhausted when the event itself is full', function () {
    $event = Event::factory()->published()->create(['capacity' => 2, 'title' => 'Full Event']);
    $ticket = freeTicket($event, ['capacity' => null]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create(['quantity' => 2]);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);
})->throws(CapacityExhausted::class, "'Full Event' is sold out");

it('returns the existing registration when the idempotency key is reused with same payload', function () {
    $event = Event::factory()->published()->create();
    $ticket = freeTicket($event);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $first = $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com'], idempotencyKey: 'key-1');
    $second = $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com'], idempotencyKey: 'key-1');

    expect($second->id)->toBe($first->id);
    expect(Registration::query()->count())->toBe(1);
});

it('throws IdempotencyConflict when the same key is reused with a different payload', function () {
    $event = Event::factory()->published()->create(['status' => EventStatus::Published]);
    $ticketA = freeTicket($event, ['name' => 'A']);
    $ticketB = freeTicket($event, ['name' => 'B']);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticketA, $user, ['name' => 'Jane', 'email' => 'jane@example.com'], idempotencyKey: 'key-2');
    $action($event, $ticketB, $user, ['name' => 'Jane', 'email' => 'jane@example.com'], idempotencyKey: 'key-2');
})->throws(IdempotencyConflict::class);

it('refuses when ticket type belongs to a different event', function () {
    $event = Event::factory()->published()->create();
    $other = Event::factory()->published()->create();
    $ticket = freeTicket($other);
    $user = User::factory()->create();

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);
    $action($event, $ticket, $user, ['name' => 'Jane', 'email' => 'jane@example.com']);
})->throws(InvalidArgumentException::class);
