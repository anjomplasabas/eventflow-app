<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $event = Event::factory();

        return [
            'registration_id' => Registration::factory(),
            'event_id' => $event,
            'ticket_type_id' => null,
            'attendee_name' => fake()->name(),
            'attendee_email' => fake()->safeEmail(),
            'status' => 'issued',
            'issued_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }
}
