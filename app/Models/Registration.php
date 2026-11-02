<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Policies\RegistrationPolicy;
use Carbon\CarbonInterface;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property int $event_id
 * @property int $ticket_type_id
 * @property int|null $user_id
 * @property RegistrationStatus $status
 * @property int $quantity
 * @property string|null $idempotency_key
 * @property CarbonInterface|null $terms_accepted_at
 * @property CarbonInterface|null $confirmed_at
 * @property CarbonInterface|null $cancelled_at
 * @property CarbonInterface|null $waitlisted_at
 * @property int|null $waitlist_position
 * @property CarbonInterface|null $checked_in_at
 * @property CarbonInterface|null $reminder_sent_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Event $event
 * @property-read TicketType $ticketType
 * @property-read User|null $user
 */
#[Fillable([
    'reference', 'event_id', 'ticket_type_id', 'user_id', 'status', 'quantity',
    'idempotency_key', 'terms_accepted_at', 'confirmed_at', 'cancelled_at',
    'waitlisted_at', 'waitlist_position', 'checked_in_at', 'reminder_sent_at',
])]
#[UsePolicy(RegistrationPolicy::class)]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $registration): void {
            if (empty($registration->reference)) {
                $registration->reference = self::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'REG-'.Str::upper(Str::random(10));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'terms_accepted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'waitlisted_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<RegistrationAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(RegistrationAttendee::class);
    }

    /**
     * @return HasMany<SeatReservation, $this>
     */
    public function seatReservations(): HasMany
    {
        return $this->hasMany(SeatReservation::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
