<x-mail::message>
# Refund issued

A refund of **{{ $refund->amountMoney()->format() }}** has been issued for your registration to **{{ $event->title }}**.

- **Refund reference:** {{ $refund->reference }}
- **Original payment reference:** {{ $payment->reference }}
- **Registration reference:** {{ $registration->reference }}
@if ($refund->reason)
- **Reason:** {{ $refund->reason }}
@endif

If you have any questions, reach out to the organizer at {{ $organizer->contact_email }}.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
