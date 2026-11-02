<?php

namespace App\Actions\Receipts;

use App\Enums\ReceiptStatus;
use App\Jobs\GenerateReceiptPdf;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Registration;
use App\Notifications\ReceiptIssuedNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class IssueReceipt
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Issue (or return the existing) receipt for a registration. The receipt
     * is an immutable snapshot of the organizer / event / attendee at the
     * moment of issue — modifying the live records afterwards does not
     * change what the attendee was handed.
     *
     * Idempotent: calling it twice for the same registration returns the
     * first receipt.
     */
    public function __invoke(Registration $registration, ?Payment $payment = null): Receipt
    {
        return DB::transaction(function () use ($registration, $payment) {
            $existing = Receipt::query()
                ->where('registration_id', $registration->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $registration->loadMissing(['event.organizer', 'ticketType', 'attendees']);

            $ticketType = $registration->ticketType;
            $event = $registration->event;
            $organizer = $event->organizer;
            $attendee = $registration->attendees->first();

            $unitPrice = $ticketType->price_minor;
            $quantity = $registration->quantity;
            $lineTotal = $unitPrice * $quantity;
            $currency = $ticketType->currency;

            $receipt = Receipt::query()->create([
                'registration_id' => $registration->id,
                'payment_id' => $payment?->id,
                'organizer_snapshot' => [
                    'id' => $organizer->id,
                    'name' => $organizer->name,
                    'contact_email' => $organizer->contact_email,
                    'contact_phone' => $organizer->contact_phone,
                ],
                'event_snapshot' => [
                    'id' => $event->id,
                    'public_id' => $event->public_id,
                    'title' => $event->title,
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'ends_at' => $event->ends_at->toIso8601String(),
                    'timezone' => $event->timezone,
                ],
                'attendee_snapshot' => [
                    'name' => $attendee?->name,
                    'email' => $attendee?->email,
                    'phone' => $attendee?->phone,
                    'registration_reference' => $registration->reference,
                ],
                'subtotal_minor' => $lineTotal,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'fee_minor' => 0,
                'total_minor' => $lineTotal,
                'currency' => $currency,
                'status' => ReceiptStatus::Issued,
                'issued_at' => now(),
            ]);

            $receipt->items()->create([
                'label' => $ticketType->name,
                'description' => $ticketType->description,
                'quantity' => $quantity,
                'unit_price_minor' => $unitPrice,
                'line_total_minor' => $lineTotal,
                'display_order' => 0,
            ]);

            $this->audit->record('receipt.issued', $receipt, after: [
                'public_number' => $receipt->public_number,
                'total_minor' => $receipt->total_minor,
                'currency' => $receipt->currency,
            ]);

            $receiptId = $receipt->id;
            $attendeeEmail = (string) ($receipt->attendee_snapshot['email'] ?? '');
            $attendeeName = (string) ($receipt->attendee_snapshot['name'] ?? '');

            DB::afterCommit(function () use ($receiptId, $attendeeEmail, $attendeeName): void {
                GenerateReceiptPdf::dispatch($receiptId);
                if ($attendeeEmail !== '') {
                    $receipt = Receipt::query()->findOrFail($receiptId);
                    Notification::route('mail', [$attendeeEmail => $attendeeName])
                        ->notify(new ReceiptIssuedNotification($receipt));
                }
            });

            return $receipt;
        });
    }
}
