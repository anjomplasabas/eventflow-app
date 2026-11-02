<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Policies\EventPolicy;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $organizer_id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string|null $category
 * @property string|null $cover_image_path
 * @property EventStatus $status
 * @property EventVisibility $visibility
 * @property string|null $access_code
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $timezone
 * @property Carbon|null $registration_opens_at
 * @property Carbon|null $registration_closes_at
 * @property int|null $capacity
 * @property int $per_attendee_limit
 * @property string|null $cancellation_policy
 * @property string|null $attendee_instructions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organizer $organizer
 */
#[Fillable([
    'organizer_id',
    'slug',
    'title',
    'description',
    'category',
    'cover_image_path',
    'status',
    'visibility',
    'access_code',
    'starts_at',
    'ends_at',
    'timezone',
    'registration_opens_at',
    'registration_closes_at',
    'capacity',
    'per_attendee_limit',
    'cancellation_policy',
    'attendee_instructions',
])]
#[UsePolicy(EventPolicy::class)]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (empty($event->public_id)) {
                $event->public_id = (string) Str::ulid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'visibility' => EventVisibility::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organizer, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * @return HasMany<TicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }
}
