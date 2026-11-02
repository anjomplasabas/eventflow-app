<?php

namespace App\Actions\Payments;

use App\Actions\Receipts\IssueReceipt;
use App\Actions\Tickets\IssueTickets;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\Payment;
use App\Models\Registration;
use App\Notifications\RegistrationConfirmedNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RecordPaymentSucceeded
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Payment $payment, ?string $providerReference = null, ?string $rawEventId = null): Payment
    {
        $notifyRegistrationId = null;

        $result = DB::transaction(function () use ($payment, $providerReference, $rawEventId, &$notifyRegistrationId) {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === PaymentStatus::Paid) {
                return $locked;
            }

            if (! $locked->status->canTransitionTo(PaymentStatus::Paid)) {
                throw new InvalidEventStateTransition(
                    "Payment in status '{$locked->status->value}' cannot be marked paid."
                );
            }

            $before = ['status' => $locked->status->value];
            $locked->status = PaymentStatus::Paid;
            $locked->paid_at = now();
            if ($providerReference !== null) {
                $locked->provider_reference = $providerReference;
            }
            if ($rawEventId !== null) {
                $locked->raw_event_id = $rawEventId;
            }
            $locked->save();

            $this->audit->record('payment.succeeded', $locked, before: $before, after: [
                'status' => $locked->status->value,
            ]);

            /** @var Registration $registration */
            $registration = Registration::query()->lockForUpdate()->findOrFail($locked->registration_id);
            if ($registration->status === RegistrationStatus::PaymentPending) {
                $regBefore = ['status' => $registration->status->value];
                $registration->status = RegistrationStatus::Confirmed;
                $registration->confirmed_at = now();
                $registration->save();

                $this->audit->record('registration.confirmed', $registration, before: $regBefore, after: [
                    'status' => $registration->status->value,
                ]);

                $notifyRegistrationId = $registration->id;
            }

            return $locked;
        });

        if ($notifyRegistrationId !== null) {
            $registration = Registration::query()
                ->with(['event.organizer', 'ticketType', 'attendees'])
                ->findOrFail($notifyRegistrationId);
            app(IssueReceipt::class)($registration, $result);
            app(IssueTickets::class)($registration);
            $attendee = $registration->attendees->first();
            if ($attendee !== null) {
                Notification::route('mail', [$attendee->email => $attendee->name])
                    ->notify(new RegistrationConfirmedNotification($registration));
            }
        }

        return $result;
    }
}
