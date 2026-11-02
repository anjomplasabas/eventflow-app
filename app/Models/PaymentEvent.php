<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PaymentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $provider_event_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property CarbonInterface $received_at
 * @property CarbonInterface|null $processed_at
 * @property array<string, mixed>|null $failure
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'provider', 'provider_event_id', 'event_type', 'payload',
    'received_at', 'processed_at', 'failure',
])]
class PaymentEvent extends Model
{
    /** @use HasFactory<PaymentEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failure' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
