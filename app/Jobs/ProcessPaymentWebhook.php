<?php

namespace App\Jobs;

use App\Actions\Payments\RecordPaymentFailed;
use App\Actions\Payments\RecordPaymentSucceeded;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableFoundation;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use QueueableFoundation, SerializesModels;

    public function __construct(public readonly int $paymentEventId)
    {
        $this->onQueue('critical');
    }

    public function handle(RecordPaymentSucceeded $succeeded, RecordPaymentFailed $failed): void
    {
        /** @var PaymentEvent $event */
        $event = PaymentEvent::query()->findOrFail($this->paymentEventId);
        if ($event->processed_at !== null) {
            return;
        }

        try {
            $paymentReference = (string) ($event->payload['payment_reference'] ?? '');
            $payment = Payment::query()->where('reference', $paymentReference)->first();
            if ($payment === null) {
                $event->failure = ['error' => "payment with reference '{$paymentReference}' not found"];
                $event->save();

                return;
            }

            $providerRef = isset($event->payload['data']['provider_reference'])
                ? (string) $event->payload['data']['provider_reference']
                : null;

            match ($event->event_type) {
                'payment.succeeded' => ($succeeded)($payment, $providerRef, $event->provider_event_id),
                'payment.failed' => ($failed)($payment, $event->provider_event_id, 'Payment failed via webhook'),
                default => null,
            };

            $event->processed_at = now();
            $event->failure = null;
            $event->save();
        } catch (Throwable $e) {
            $event->failure = [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ];
            $event->save();
            throw $e;
        }
    }
}
