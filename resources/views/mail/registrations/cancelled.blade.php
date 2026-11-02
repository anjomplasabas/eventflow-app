<x-mail::message>
# Registration cancelled

Your registration for **{{ $event->title }}** has been cancelled.

- **Reference:** {{ $registration->reference }}
- **Ticket:** {{ $registration->ticketType->name }}
@if ($reason)
- **Reason:** {{ $reason }}
@endif

If this was unexpected, reach out to the organizer at {{ $organizer->contact_email }}.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
