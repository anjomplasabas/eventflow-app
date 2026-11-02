<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
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
            'user_id' => User::factory(),
            'status' => RegistrationStatus::Pending,
            'quantity' => 1,
            'idempotency_key' => null,
            'terms_accepted_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function waitlisted(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::Waitlisted,
            'waitlisted_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::CheckedIn,
            'confirmed_at' => now()->subHour(),
            'checked_in_at' => now(),
        ]);
    }
}
