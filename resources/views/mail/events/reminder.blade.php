<x-mail::message>
# Starting soon: {{ $event->title }}

Just a reminder that your event is coming up.

- **Starts:** {{ $event->starts_at->format('l, F j, Y g:i A') }} ({{ $event->timezone }})
- **Reference:** {{ $registration->reference }}
- **Host:** {{ $organizer->name }}

<x-mail::button :url="url('/me/registrations/' . $registration->reference)">
View registration
</x-mail::button>

See you soon!<br>
{{ config('app.name') }}
</x-mail::message>
