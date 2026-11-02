<?php

namespace App\Models;

use App\Enums\CheckInMethod;
use App\Policies\CheckInPolicy;
use Carbon\CarbonInterface;
use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $registration_id
 * @property int $event_id
 * @property int|null $staff_user_id
 * @property CheckInMethod $method
 * @property CarbonInterface $scanned_at
 * @property CarbonInterface|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversed_reason
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Ticket $ticket
 * @property-read Registration $registration
 * @property-read Event $event
 * @property-read User|null $staff
 */
#[Fillable([
    'ticket_id', 'registration_id', 'event_id', 'staff_user_id',
    'method', 'scanned_at', 'reversed_at', 'reversed_by', 'reversed_reason',
])]
#[UsePolicy(CheckInPolicy::class)]
class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => CheckInMethod::class,
            'scanned_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
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
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }
}
