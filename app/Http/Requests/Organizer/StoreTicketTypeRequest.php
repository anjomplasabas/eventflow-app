<?php

namespace App\Http\Requests\Organizer;

use App\Enums\TicketKind;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            && $this->user()?->can('createForEvent', [TicketType::class, $event]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $eventId = $event instanceof Event ? $event->id : null;

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('ticket_types', 'name')->where('event_id', $eventId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::enum(TicketKind::class)],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'min_per_order' => ['required', 'integer', 'min:1'],
            'max_per_order' => ['required', 'integer', 'min:1', 'gte:min_per_order'],
            'sales_opens_at' => ['nullable', 'date'],
            'sales_closes_at' => ['nullable', 'date', 'after:sales_opens_at'],
            'active' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('kind') === TicketKind::Free->value && (int) $this->input('price_minor', 0) !== 0) {
                $v->errors()->add('price_minor', 'Free tickets must have a price of 0.');
            }
        });
    }
}
