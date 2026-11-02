<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $eventId = $event instanceof Event ? $event->id : null;

        return [
            'ticket_type_id' => [
                'required', 'integer',
                Rule::exists('ticket_types', 'id')
                    ->where('event_id', $eventId)
                    ->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'terms_accepted' => ['required', 'accepted'],
            'attendee.name' => ['required', 'string', 'max:120'],
            'attendee.email' => ['required', 'email:rfc', 'max:255'],
            'attendee.phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function ticketType(): TicketType
    {
        return TicketType::query()->findOrFail($this->integer('ticket_type_id'));
    }

    /**
     * @return array{name: string, email: string, phone: string|null}
     */
    public function attendee(): array
    {
        /** @var array{name: string, email: string, phone?: string|null} $attendee */
        $attendee = $this->input('attendee');

        return [
            'name' => $attendee['name'],
            'email' => $attendee['email'],
            'phone' => $attendee['phone'] ?? null,
        ];
    }
}
