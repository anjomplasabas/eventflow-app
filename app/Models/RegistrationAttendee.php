<?php

namespace App\Models;

use Database\Factories\RegistrationAttendeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $registration_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property array<string, mixed>|null $custom_answers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 */
#[Fillable(['registration_id', 'name', 'email', 'phone', 'custom_answers'])]
class RegistrationAttendee extends Model
{
    /** @use HasFactory<RegistrationAttendeeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_answers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
