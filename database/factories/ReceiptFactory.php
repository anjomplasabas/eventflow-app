<?php

namespace Database\Factories;

use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'payment_id' => null,
            'organizer_snapshot' => ['name' => 'Fake Organizer'],
            'event_snapshot' => ['title' => 'Fake Event'],
            'attendee_snapshot' => ['name' => 'Fake Attendee', 'email' => 'a@x.test'],
            'subtotal_minor' => 1000,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'fee_minor' => 0,
            'total_minor' => 1000,
            'currency' => 'USD',
            'status' => ReceiptStatus::Issued,
            'issued_at' => now(),
        ];
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => ReceiptStatus::Voided,
            'voided_at' => now(),
            'voided_reason' => 'Issued in error',
        ]);
    }
}
