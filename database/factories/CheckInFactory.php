<?php

namespace Database\Factories;

use App\Enums\CheckInMethod;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'registration_id' => Registration::factory(),
            'event_id' => Event::factory(),
            'staff_user_id' => null,
            'method' => CheckInMethod::Qr,
            'scanned_at' => now(),
        ];
    }
}
