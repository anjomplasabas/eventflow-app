<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);
        $starts = fake()->dateTimeBetween('+1 week', '+2 months');
        $ends = (clone $starts)->modify('+2 hours');
        // Keep registration open "now" by default; tests can override.
        $registrationOpens = now()->subWeek()->toDateTime();
        $registrationCloses = (clone $starts)->modify('-1 hour');

        return [
            'organizer_id' => Organizer::factory(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'title' => $title,
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Conference', 'Workshop', 'Meetup', 'Webinar']),
            'cover_image_path' => null,
            'status' => EventStatus::Draft,
            'visibility' => EventVisibility::Public,
            'access_code' => null,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'timezone' => 'UTC',
            'registration_opens_at' => $registrationOpens,
            'registration_closes_at' => $registrationCloses,
            'capacity' => fake()->numberBetween(20, 500),
            'per_attendee_limit' => 1,
            'cancellation_policy' => null,
            'attendee_instructions' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Published]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Cancelled]);
    }

    public function completed(): static
    {
        return $this->state(function () {
            $starts = fake()->dateTimeBetween('-2 months', '-1 week');
            $ends = (clone $starts)->modify('+2 hours');

            return [
                'status' => EventStatus::Completed,
                'starts_at' => $starts,
                'ends_at' => $ends,
            ];
        });
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'visibility' => EventVisibility::Private,
            'access_code' => Str::upper(Str::random(8)),
        ]);
    }
}
