<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundIssuedNotification;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class IssueRefund
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(Payment $payment, int $amountMinor, ?string $reason = null, ?User $actor = null): Refund
    {
        $refundId = DB::transaction(function () use ($payment, $amountMinor, $reason, $actor) {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], strict: true)) {
                throw new InvalidEventStateTransition(
                    "Cannot refund a payment in status '{$locked->status->value}'."
                );
            }

            if ($amountMinor <= 0) {
                throw new DomainException('Refund amount must be positive.');
            }

            if ($amountMinor > $locked->remainingRefundableMinor()) {
                throw new DomainException('Refund amount exceeds the remaining refundable total.');
            }

            $result = $this->gateway->processRefund($locked, $amountMinor, $reason);
            if (! $result->succeeded()) {
                throw new DomainException('Refund was declined by the payment provider.');
            }

            $refund = Refund::query()->create([
                'payment_id' => $locked->id,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
                'reason' => $reason,
                'provider_reference' => $result->providerReference,
                'status' => 'succeeded',
                'processed_at' => now(),
            ]);

            $before = ['status' => $locked->status->value];
            $remainingAfter = $locked->amount_minor - $locked->refundedTotalMinor();
            $locked->status = $remainingAfter <= 0
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded;
            $locked->save();

            $this->audit->record('payment.refunded', $locked, before: $before, after: [
                'status' => $locked->status->value,
                'refund_id' => $refund->id,
                'amount_minor' => $amountMinor,
            ], actor: $actor);

            return $refund->id;
        });

        $refund = Refund::query()->with('payment.registration.event.organizer', 'payment.registration.attendees')->findOrFail($refundId);
        $attendee = $refund->payment->registration->attendees->first();
        if ($attendee !== null) {
            Notification::route('mail', [$attendee->email => $attendee->name])
                ->notify(new RefundIssuedNotification($refund));
        }

        return $refund;
    }
}
