<?php

namespace Database\Factories;

use App\Models\PaymentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentEvent>
 */
class PaymentEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'fake',
            'provider_event_id' => 'evt_'.fake()->uuid(),
            'event_type' => 'payment.succeeded',
            'payload' => ['raw' => true],
            'received_at' => now(),
        ];
    }
}
