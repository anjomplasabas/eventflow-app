<?php

use App\Actions\Registrations\CreateRegistration;
use App\Enums\RegistrationStatus;
use App\Exceptions\CapacityExhausted;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;

/**
 * Capacity stress: push 20 attempts at a ticket type with capacity 3 and
 * assert that we end up with exactly 3 confirmed registrations and 17
 * CapacityExhausted exceptions. Serial on SQLite; the point is to prove
 * the business invariant holds under volume regardless of concurrency.
 */
it('never oversells no matter how many attempts hit the same ticket type', function () {
    $event = Event::factory()->published()->create(['capacity' => null, 'waitlist_enabled' => false]);
    $ticket = TicketType::factory()->for($event)->free()->create([
        'capacity' => 3, 'min_per_order' => 1, 'max_per_order' => 1,
    ]);

    /** @var CreateRegistration $action */
    $action = app(CreateRegistration::class);

    $successes = 0;
    $failures = 0;
    for ($i = 0; $i < 20; $i++) {
        try {
            $action($event, $ticket, User::factory()->create(), [
                'name' => "Attendee {$i}",
                'email' => "a{$i}@stress.test",
            ]);
            $successes++;
        } catch (CapacityExhausted) {
            $failures++;
        }
    }

    expect($successes)->toBe(3)
        ->and($failures)->toBe(17)
        ->and(Registration::query()->where('status', RegistrationStatus::Confirmed)->count())->toBe(3);
});
