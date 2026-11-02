<?php

namespace App\Actions\Payments;

use App\Actions\Registrations\CancelRegistration;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class RecordPaymentFailed
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CancelRegistration $cancel,
    ) {}

    public function __invoke(Payment $payment, ?string $rawEventId = null, ?string $reason = null): Payment
    {
        $registrationId = DB::transaction(function () use ($payment, $rawEventId): ?int {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === PaymentStatus::Failed) {
                return null;
            }

            if (! $locked->status->canTransitionTo(PaymentStatus::Failed)) {
                return null;
            }

            $before = ['status' => $locked->status->value];
            $locked->status = PaymentStatus::Failed;
            $locked->failed_at = now();
            if ($rawEventId !== null) {
                $locked->raw_event_id = $rawEventId;
            }
            $locked->save();

            $this->audit->record('payment.failed', $locked, before: $before, after: [
                'status' => $locked->status->value,
            ]);

            return $locked->registration_id;
        });

        if ($registrationId !== null) {
            $registration = Registration::query()->findOrFail($registrationId);
            if ($registration->status === RegistrationStatus::PaymentPending) {
                ($this->cancel)($registration, null, $reason ?? 'Payment failed');
            }
        }

        return $payment->fresh() ?? $payment;
    }
}
