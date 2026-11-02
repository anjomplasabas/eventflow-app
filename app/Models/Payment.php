<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property int $registration_id
 * @property string $provider
 * @property string|null $provider_reference
 * @property int $amount_minor
 * @property string $currency
 * @property int $fee_minor
 * @property PaymentStatus $status
 * @property array<string, mixed>|null $method_summary
 * @property string|null $raw_event_id
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $failed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Registration $registration
 */
#[Fillable([
    'reference', 'registration_id', 'provider', 'provider_reference',
    'amount_minor', 'currency', 'fee_minor', 'status',
    'method_summary', 'raw_event_id', 'paid_at', 'failed_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (empty($payment->reference)) {
                $payment->reference = 'PAY-'.Str::upper(Str::random(10));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'method_summary' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function amountMoney(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    public function refundedTotalMinor(): int
    {
        return (int) $this->refunds()->where('status', 'succeeded')->sum('amount_minor');
    }

    public function remainingRefundableMinor(): int
    {
        return max(0, $this->amount_minor - $this->refundedTotalMinor());
    }
}
