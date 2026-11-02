<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Contracts\Payments\PaymentGateway;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class InitializeCheckout
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{payment: Payment, session: CheckoutSession}
     */
    public function __invoke(Registration $registration): array
    {
        return DB::transaction(function () use ($registration): array {
            $ticketType = $registration->ticketType;
            $amount = $ticketType->priceMoney()->multiplyByQuantity($registration->quantity);

            $payment = Payment::query()->create([
                'registration_id' => $registration->id,
                'provider' => $this->gateway->name(),
                'amount_minor' => $amount->minorUnits,
                'currency' => $amount->currency,
            ]);

            $session = $this->gateway->createCheckoutSession($payment);
            $payment->provider_reference = $session->providerReference;
            $payment->save();

            $this->audit->record('payment.initialized', $payment, after: [
                'reference' => $payment->reference,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
            ]);

            return ['payment' => $payment, 'session' => $session];
        });
    }
}
