<x-mail::message>
# You're on the waitlist

Thanks for your interest in **{{ $event->title }}**. The event is currently full, but we've added you to the waitlist.

- **Reference:** {{ $registration->reference }}
- **Ticket:** {{ $registration->ticketType->name }}
- **Position:** #{{ $registration->waitlist_position ?? '—' }}
- **Host:** {{ $organizer->name }}

If space opens up we'll email you right away.

<x-mail::button :url="url('/me/registrations/' . $registration->reference)">
View registration
</x-mail::button>

Thanks for your patience,<br>
{{ config('app.name') }}
</x-mail::message>
