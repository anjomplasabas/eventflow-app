<?php

namespace App\Services\Payments;

use App\Contracts\Payments\CheckoutSession;
use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\RefundResult;
use App\Contracts\Payments\WebhookEvent;
use App\Models\Payment;
use InvalidArgumentException;

/**
 * Fake sandbox adapter. Signatures are HMAC-SHA256 over the payload using a
 * pre-shared secret so webhook integration tests exercise the full validation
 * path without hitting a real provider.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $secret,
    ) {}

    public function name(): string
    {
        return 'fake';
    }

    public function createCheckoutSession(Payment $payment): CheckoutSession
    {
        $providerReference = 'cs_'.bin2hex(random_bytes(10));

        return new CheckoutSession(
            providerReference: $providerReference,
            redirectUrl: route('payments.sandbox.show', ['reference' => $payment->reference]),
        );
    }

    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        return hash_equals($this->sign($payload), $signature);
    }

    public function parseWebhookEvent(string $payload): WebhookEvent
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($payload, associative: true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Webhook payload is not valid JSON.');
        }

        foreach (['id', 'type', 'payment_reference'] as $required) {
            if (! array_key_exists($required, $decoded)) {
                throw new InvalidArgumentException("Webhook payload missing '{$required}'.");
            }
        }

        /** @var array<string, mixed> $data */
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];

        return new WebhookEvent(
            providerEventId: (string) $decoded['id'],
            type: (string) $decoded['type'],
            paymentReference: (string) $decoded['payment_reference'],
            data: $data,
        );
    }

    public function processRefund(Payment $payment, int $amountMinor, ?string $reason = null): RefundResult
    {
        if ($amountMinor <= 0 || $amountMinor > $payment->remainingRefundableMinor()) {
            return new RefundResult('', 'failed');
        }

        return new RefundResult(
            providerReference: 're_'.bin2hex(random_bytes(10)),
            status: 'succeeded',
        );
    }
}
