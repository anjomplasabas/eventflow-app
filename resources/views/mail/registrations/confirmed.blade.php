<x-mail::message>
# You're registered!

Thanks, {{ $registration->attendees->first()?->name ?? 'friend' }} — your registration for **{{ $event->title }}** is confirmed.

- **Reference:** {{ $registration->reference }}
- **Ticket:** {{ $registration->ticketType->name }}
- **Quantity:** {{ $registration->quantity }}
- **Starts:** {{ $event->starts_at->format('l, F j, Y g:i A') }} ({{ $event->timezone }})
- **Host:** {{ $organizer->name }}

<x-mail::button :url="url('/me/registrations/' . $registration->reference)">
View registration
</x-mail::button>

See you there!<br>
{{ config('app.name') }}
</x-mail::message>
