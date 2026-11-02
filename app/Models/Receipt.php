<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_number
 * @property int $registration_id
 * @property int|null $payment_id
 * @property array<string, mixed> $organizer_snapshot
 * @property array<string, mixed> $event_snapshot
 * @property array<string, mixed> $attendee_snapshot
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $tax_minor
 * @property int $fee_minor
 * @property int $total_minor
 * @property string $currency
 * @property ReceiptStatus $status
 * @property string|null $pdf_path
 * @property CarbonInterface $issued_at
 * @property CarbonInterface|null $voided_at
 * @property string|null $voided_reason
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Registration $registration
 * @property-read Payment|null $payment
 */
#[Fillable([
    'public_number', 'registration_id', 'payment_id',
    'organizer_snapshot', 'event_snapshot', 'attendee_snapshot',
    'subtotal_minor', 'discount_minor', 'tax_minor', 'fee_minor', 'total_minor', 'currency',
    'status', 'pdf_path', 'issued_at', 'voided_at', 'voided_reason',
])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            if (empty($receipt->public_number)) {
                $receipt->public_number = self::generatePublicNumber();
            }
        });
    }

    public static function generatePublicNumber(): string
    {
        // Non-sequential, URL-safe, uppercase. e.g. RCPT-7HJK3QX1
        return 'RCPT-'.Str::upper(Str::random(10));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'organizer_snapshot' => 'array',
            'event_snapshot' => 'array',
            'attendee_snapshot' => 'array',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return HasMany<ReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }

    public function totalMoney(): Money
    {
        return Money::of($this->total_minor, $this->currency);
    }

    public function subtotalMoney(): Money
    {
        return Money::of($this->subtotal_minor, $this->currency);
    }
}
