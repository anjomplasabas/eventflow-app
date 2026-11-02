<?php

namespace App\Contracts\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    public function name(): string;

    public function createCheckoutSession(Payment $payment): CheckoutSession;

    public function verifyWebhookSignature(string $payload, string $signature): bool;

    public function parseWebhookEvent(string $payload): WebhookEvent;

    public function processRefund(Payment $payment, int $amountMinor, ?string $reason = null): RefundResult;
}
