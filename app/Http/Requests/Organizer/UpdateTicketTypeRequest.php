<?php

namespace App\Http\Requests\Organizer;

use App\Enums\TicketKind;
use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticketType = $this->route('ticket_type');

        return $ticketType instanceof TicketType
            && $this->user()?->can('update', $ticketType) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ticketType = $this->route('ticket_type');
        $eventId = $ticketType instanceof TicketType ? $ticketType->event_id : null;
        $ticketTypeId = $ticketType instanceof TicketType ? $ticketType->id : null;

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:120',
                Rule::unique('ticket_types', 'name')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at')
                    ->ignore($ticketTypeId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['sometimes', 'required', Rule::enum(TicketKind::class)],
            'price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'min_per_order' => ['sometimes', 'required', 'integer', 'min:1'],
            'max_per_order' => ['sometimes', 'required', 'integer', 'min:1', 'gte:min_per_order'],
            'sales_opens_at' => ['nullable', 'date'],
            'sales_closes_at' => ['nullable', 'date', 'after:sales_opens_at'],
            'active' => ['sometimes', 'required', 'boolean'],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $kind = $this->input('kind');
            if ($kind === TicketKind::Free->value && (int) $this->input('price_minor', 0) !== 0) {
                $v->errors()->add('price_minor', 'Free tickets must have a price of 0.');
            }
        });
    }
}
