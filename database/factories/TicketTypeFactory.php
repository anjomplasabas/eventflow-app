<?php

namespace Database\Factories;

use App\Enums\TicketKind;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['General Admission', 'VIP', 'Student', 'Early Bird']),
            'description' => fake()->optional()->sentence(),
            'kind' => TicketKind::Free,
            'price_minor' => 0,
            'currency' => 'USD',
            'capacity' => fake()->numberBetween(20, 200),
            'min_per_order' => 1,
            'max_per_order' => fake()->numberBetween(1, 5),
            'sales_opens_at' => null,
            'sales_closes_at' => null,
            'active' => true,
            'display_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'kind' => TicketKind::Free,
            'price_minor' => 0,
        ]);
    }

    public function paid(int $priceMinor = 2500, string $currency = 'USD'): static
    {
        return $this->state(fn () => [
            'kind' => TicketKind::Paid,
            'price_minor' => $priceMinor,
            'currency' => $currency,
        ]);
    }
}
