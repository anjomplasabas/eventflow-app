<?php

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\SeatReservation;
use App\Models\TicketType;
use App\Services\Registration\CapacityCalculator;

it('returns null when the event has no capacity cap', function () {
    $event = Event::factory()->published()->create(['capacity' => null]);

    $calc = app(CapacityCalculator::class);
    expect($calc->eventRemaining($event))->toBeNull();
});

it('counts confirmed and pending registrations against event capacity', function () {
    $event = Event::factory()->published()->create(['capacity' => 10]);
    $tt = TicketType::factory()->for($event)->create(['capacity' => null]);
    Registration::factory()->for($event)->for($tt)->confirmed()->count(3)->create(['quantity' => 1]);
    Registration::factory()->for($event)->for($tt)->create(['status' => RegistrationStatus::Pending, 'quantity' => 2]);
    Registration::factory()->for($event)->for($tt)->cancelled()->create(['quantity' => 5]); // cancelled doesn't occupy

    $calc = app(CapacityCalculator::class);
    expect($calc->eventRemaining($event))->toBe(5); // 10 - (3 + 2)
});

it('ignores expired seat reservations but counts active ones', function () {
    $event = Event::factory()->published()->create(['capacity' => 10]);
    $tt = TicketType::factory()->for($event)->create(['capacity' => null]);

    SeatReservation::factory()->for($event)->for($tt)->create(['quantity' => 3, 'expires_at' => now()->addMinutes(5)]);
    SeatReservation::factory()->for($event)->for($tt)->create(['quantity' => 4, 'expires_at' => now()->subMinute()]); // expired

    $calc = app(CapacityCalculator::class);
    expect($calc->eventRemaining($event))->toBe(7);
});

it('takes the smaller of event vs ticket-type remaining', function () {
    $event = Event::factory()->published()->create(['capacity' => 100]);
    $tt = TicketType::factory()->for($event)->create(['capacity' => 5]);
    Registration::factory()->for($event)->for($tt)->confirmed()->create(['quantity' => 2]);

    $calc = app(CapacityCalculator::class);
    expect($calc->ticketTypeRemaining($tt))->toBe(3)
        ->and($calc->eventRemaining($event))->toBe(98)
        ->and($calc->effectiveRemaining($event, $tt))->toBe(3);
});

it('reports null for effective remaining when nothing is capped', function () {
    $event = Event::factory()->published()->create(['capacity' => null]);
    $tt = TicketType::factory()->for($event)->create(['capacity' => null]);

    $calc = app(CapacityCalculator::class);
    expect($calc->effectiveRemaining($event, $tt))->toBeNull()
        ->and($calc->canFit($event, $tt, 999))->toBeTrue();
});

it('reports canFit false when quantity exceeds remaining', function () {
    $event = Event::factory()->published()->create(['capacity' => 2]);
    $tt = TicketType::factory()->for($event)->create(['capacity' => null]);
    Registration::factory()->for($event)->for($tt)->confirmed()->create(['quantity' => 1]);

    $calc = app(CapacityCalculator::class);
    expect($calc->canFit($event, $tt, 2))->toBeFalse()
        ->and($calc->canFit($event, $tt, 1))->toBeTrue();
});
