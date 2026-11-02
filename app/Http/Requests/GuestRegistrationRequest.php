<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guest endpoint — any visitor without a session may submit.
        return $this->user() === null;
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
            'access_code' => ['nullable', 'string', 'max:64'],
            'attendee.name' => ['required', 'string', 'max:120'],
            'attendee.email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email'),
            ],
            'attendee.phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attendee.email.unique' => 'That email already has an account — please sign in to continue.',
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
