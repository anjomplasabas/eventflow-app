<?php

use App\Actions\Registrations\CancelRegistration;
use App\Enums\RegistrationStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;

it('cancels a confirmed registration via the action and audits it', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $user = User::factory()->create();
    $registration = Registration::factory()->for($event)->for($ticket)->for($user)->confirmed()->create();

    /** @var CancelRegistration $action */
    $action = app(CancelRegistration::class);
    $action($registration, $user, 'changed my mind');

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Cancelled)
        ->and($registration->fresh()->cancelled_at)->not->toBeNull();

    expect(AuditLog::query()->where('action', 'registration.cancelled')->exists())->toBeTrue();
});

it('refuses to cancel a registration that is already terminal', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $user = User::factory()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)->for($user)
        ->create(['status' => RegistrationStatus::Expired]);

    /** @var CancelRegistration $action */
    $action = app(CancelRegistration::class);
    $action($registration, $user);
})->throws(InvalidEventStateTransition::class);

it('lets a user cancel their own registration via the endpoint', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $user = User::factory()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)->for($user)->confirmed()->create();

    $this->actingAs($user)
        ->post("/me/registrations/{$registration->reference}/cancel", ['reason' => 'conflict'])
        ->assertRedirect("/me/registrations/{$registration->reference}");

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Cancelled);
});

it('denies cancelling another user registration', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)->for($owner)->confirmed()->create();

    $this->actingAs($stranger)
        ->post("/me/registrations/{$registration->reference}/cancel")
        ->assertForbidden();
});
