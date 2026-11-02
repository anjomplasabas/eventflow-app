<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organizer_id
 * @property int $user_id
 * @property string $type
 * @property array<string, mixed>|null $filters
 * @property string $status
 * @property string|null $file_path
 * @property string|null $filename
 * @property int|null $row_count
 * @property string|null $failure_reason
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'uuid', 'organizer_id', 'user_id', 'type', 'filters',
    'status', 'file_path', 'filename', 'row_count',
    'failure_reason', 'expires_at', 'completed_at',
])]
class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            if (empty($export->uuid)) {
                $export->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
