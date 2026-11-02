<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'amount_minor' => 1000,
            'currency' => 'USD',
            'reason' => null,
            'status' => 'succeeded',
            'processed_at' => now(),
        ];
    }
}
