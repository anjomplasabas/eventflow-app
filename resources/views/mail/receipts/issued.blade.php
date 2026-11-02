<x-mail::message>
# Your receipt is ready

@php
    $event = $receipt->event_snapshot;
    $organizer = $receipt->organizer_snapshot;
    $total = \App\Support\Money::of($receipt->total_minor, $receipt->currency);
@endphp

Thanks for registering for **{{ $event['title'] ?? 'your event' }}**. Here's your receipt.

- **Receipt number:** {{ $receipt->public_number }}
- **Total:** {{ $total->format() }}
- **Host:** {{ $organizer['name'] ?? '' }}

<x-mail::button :url="url('/me/receipts/' . $receipt->public_number)">
View receipt
</x-mail::button>

Keep this email for your records.<br>
{{ config('app.name') }}
</x-mail::message>
