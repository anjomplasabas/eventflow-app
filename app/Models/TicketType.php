<?php

namespace App\Models;

use App\Enums\TicketKind;
use App\Policies\TicketTypePolicy;
use App\Support\Money;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property string|null $description
 * @property TicketKind $kind
 * @property int $price_minor
 * @property string $currency
 * @property int|null $capacity
 * @property int $min_per_order
 * @property int $max_per_order
 * @property Carbon|null $sales_opens_at
 * @property Carbon|null $sales_closes_at
 * @property bool $active
 * @property int $display_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Event $event
 */
#[Fillable([
    'event_id', 'name', 'description', 'kind', 'price_minor', 'currency',
    'capacity', 'min_per_order', 'max_per_order', 'sales_opens_at', 'sales_closes_at',
    'active', 'display_order',
])]
#[UsePolicy(TicketTypePolicy::class)]
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TicketKind::class,
            'sales_opens_at' => 'datetime',
            'sales_closes_at' => 'datetime',
            'active' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function priceMoney(): Money
    {
        return Money::of($this->price_minor, $this->currency);
    }

    public function isFree(): bool
    {
        return $this->kind === TicketKind::Free;
    }
}
