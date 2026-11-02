<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt->public_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 32px; font-size: 11pt; line-height: 1.4; }
        .brand { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #cffafe; color: #0e7490; font-weight: 600; font-size: 10pt; }
        h1 { margin: 16px 0 4px; font-size: 20pt; }
        .muted { color: #475569; }
        .divider { border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0; }
        .grid { width: 100%; margin-bottom: 24px; }
        .grid td { vertical-align: top; padding: 6px 0; }
        .grid td.label { color: #64748b; width: 150px; font-size: 10pt; text-transform: uppercase; letter-spacing: 0.03em; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td { padding: 10px 12px; text-align: left; font-size: 10pt; }
        table.items th { background: #f1f5f9; border-bottom: 1px solid #cbd5f5; }
        table.items td { border-bottom: 1px solid #e2e8f0; }
        table.items td.num, table.items th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals { width: 100%; margin-top: 16px; }
        .totals td { padding: 4px 0; font-size: 10pt; }
        .totals td.label { color: #64748b; text-align: right; padding-right: 16px; }
        .totals td.value { text-align: right; font-variant-numeric: tabular-nums; width: 120px; }
        .totals tr.grand td { font-size: 13pt; font-weight: 700; color: #0f172a; border-top: 1px solid #cbd5f5; padding-top: 10px; }
        .footer { margin-top: 32px; font-size: 9pt; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @php
        $organizer = $receipt->organizer_snapshot;
        $event = $receipt->event_snapshot;
        $attendee = $receipt->attendee_snapshot;
        $currency = $receipt->currency;
        $formatMinor = fn (int $minor) => \App\Support\Money::of($minor, $currency)->format();
    @endphp

    <span class="brand">EventFlow receipt</span>
    <h1>{{ $event['title'] ?? 'Event' }}</h1>
    <div class="muted">Hosted by {{ $organizer['name'] ?? 'Organizer' }}</div>

    <hr class="divider">

    <table class="grid">
        <tr>
            <td class="label">Receipt number</td>
            <td><strong>{{ $receipt->public_number }}</strong></td>
            <td class="label">Issued</td>
            <td>{{ $receipt->issued_at->format('M j, Y g:i A') }}</td>
        </tr>
        <tr>
            <td class="label">Registration</td>
            <td>{{ $attendee['registration_reference'] ?? '—' }}</td>
            <td class="label">Status</td>
            <td style="text-transform: capitalize">{{ str_replace('_', ' ', $receipt->status->value) }}</td>
        </tr>
        <tr>
            <td class="label">Attendee</td>
            <td colspan="3">
                {{ $attendee['name'] ?? '' }}<br>
                <span class="muted">{{ $attendee['email'] ?? '' }}</span>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Unit</th>
                <th class="num">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->label }}</strong>
                        @if ($item->description)
                            <br><span class="muted">{{ $item->description }}</span>
                        @endif
                    </td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $formatMinor($item->unit_price_minor) }}</td>
                    <td class="num">{{ $formatMinor($item->line_total_minor) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">{{ $formatMinor($receipt->subtotal_minor) }}</td>
        </tr>
        @if ($receipt->discount_minor > 0)
        <tr>
            <td class="label">Discount</td>
            <td class="value">-{{ $formatMinor($receipt->discount_minor) }}</td>
        </tr>
        @endif
        @if ($receipt->tax_minor > 0)
        <tr>
            <td class="label">Tax</td>
            <td class="value">{{ $formatMinor($receipt->tax_minor) }}</td>
        </tr>
        @endif
        @if ($receipt->fee_minor > 0)
        <tr>
            <td class="label">Fee</td>
            <td class="value">{{ $formatMinor($receipt->fee_minor) }}</td>
        </tr>
        @endif
        <tr class="grand">
            <td class="label">Total</td>
            <td class="value">{{ $formatMinor($receipt->total_minor) }}</td>
        </tr>
    </table>

    <div class="footer">
        Verify this receipt at {{ url('/verify/receipts/'.$receipt->public_number) }}<br>
        Issued by EventFlow on behalf of {{ $organizer['name'] ?? 'the organizer' }}.
    </div>
</body>
</html>
