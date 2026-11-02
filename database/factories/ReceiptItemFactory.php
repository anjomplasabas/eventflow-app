<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptItem>
 */
class ReceiptItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => Receipt::factory(),
            'label' => 'General Admission',
            'description' => null,
            'quantity' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'display_order' => 0,
        ];
    }
}
