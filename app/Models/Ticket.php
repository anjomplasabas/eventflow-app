<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_token
 * @property int $registration_id
 * @property int $event_id
 * @property int|null $ticket_type_id
 * @property string $attendee_name
 * @property string $attendee_email
 * @property string $status
 * @property CarbonInterface $issued_at
 * @property CarbonInterface|null $revoked_at
 * @property CarbonInterface|null $checked_in_at
 * @property string|null $qr_image_path
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Registration $registration
 * @property-read Event $event
 * @property-read TicketType|null $ticketType
 */
#[Fillable([
    'public_token', 'registration_id', 'event_id', 'ticket_type_id',
    'attendee_name', 'attendee_email', 'status', 'issued_at',
    'revoked_at', 'checked_in_at', 'qr_image_path',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (empty($ticket->public_token)) {
                $ticket->public_token = self::generatePublicToken();
            }
        });
    }

    public static function generatePublicToken(): string
    {
        // 32-char opaque, URL-safe. Not derived from the id.
        return Str::lower(Str::random(32));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'checked_in_at' => 'datetime',
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

    public function isActive(): bool
    {
        return $this->status === 'issued' && $this->revoked_at === null;
    }
}
