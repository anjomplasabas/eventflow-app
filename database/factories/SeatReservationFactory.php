<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\SeatReservation;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatReservation>
 */
class SeatReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $event = Event::factory();

        return [
            'event_id' => $event,
            'ticket_type_id' => TicketType::factory()->state(fn () => ['event_id' => $event]),
            'registration_id' => null,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
