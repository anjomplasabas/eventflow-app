<x-mail::message>
@if ($isConfirmed)
# You're in!

A seat opened up for **{{ $event->title }}** and we've confirmed your spot.
@else
# A seat is available

A seat opened up for **{{ $event->title }}**. Complete payment to secure your spot before the hold expires.
@endif

- **Reference:** {{ $registration->reference }}
- **Ticket:** {{ $registration->ticketType->name }}
- **Starts:** {{ $event->starts_at->format('l, F j, Y g:i A') }} ({{ $event->timezone }})
- **Host:** {{ $organizer->name }}

<x-mail::button :url="url('/me/registrations/' . $registration->reference)">
View registration
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
