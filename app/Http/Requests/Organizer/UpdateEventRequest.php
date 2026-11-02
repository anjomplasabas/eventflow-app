<?php

namespace App\Http\Requests\Organizer;

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && $this->user()?->can('update', $event) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizer = $this->route('organizer');
        $organizerId = $organizer instanceof Organizer ? $organizer->id : null;
        $event = $this->route('event');
        $eventId = $event instanceof Event ? $event->id : null;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('events', 'slug')
                    ->where('organizer_id', $organizerId)
                    ->whereNull('deleted_at')
                    ->ignore($eventId),
            ],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:64'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'visibility' => ['sometimes', 'required', Rule::enum(EventVisibility::class)],
            'access_code' => ['nullable', 'string', 'max:64', 'required_if:visibility,private'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
            'registration_opens_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'registration_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'per_attendee_limit' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
            'cancellation_policy' => ['nullable', 'string'],
            'attendee_instructions' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toEventAttributes(): array
    {
        $data = $this->safe()->except(['cover_image']);

        if ($this->hasFile('cover_image')) {
            $data['cover_image_path'] = Str::replaceFirst(
                'public/',
                '',
                (string) $this->file('cover_image')?->store('event-covers', 'public'),
            );
        }

        return $data;
    }
}
