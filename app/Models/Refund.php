<?php

namespace App\Models;

use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property int $payment_id
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $reason
 * @property string|null $provider_reference
 * @property string $status
 * @property CarbonInterface|null $processed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Payment $payment
 */
#[Fillable([
    'reference', 'payment_id', 'amount_minor', 'currency', 'reason',
    'provider_reference', 'status', 'processed_at',
])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $refund): void {
            if (empty($refund->reference)) {
                $refund->reference = 'REF-'.Str::upper(Str::random(10));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function amountMoney(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }
}
