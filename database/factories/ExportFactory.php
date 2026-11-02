<?php

namespace Database\Factories;

use App\Models\Export;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organizer_id' => Organizer::factory(),
            'user_id' => User::factory(),
            'type' => 'receipt_register',
            'filters' => null,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'file_path' => 'exports/'.fake()->uuid().'.csv',
            'filename' => 'receipts.csv',
            'row_count' => 10,
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'expires_at' => now()->subDay(),
            'file_path' => 'exports/'.fake()->uuid().'.csv',
            'completed_at' => now()->subDays(10),
        ]);
    }
}
