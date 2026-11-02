<?php

use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;

it('produces matching signatures that verify successfully', function () {
    $gateway = new FakePaymentGateway('shh');
    $payload = '{"id":"evt_1","type":"payment.succeeded","payment_reference":"PAY-ABCDE12345"}';

    $signature = $gateway->sign($payload);

    expect($gateway->verifyWebhookSignature($payload, $signature))->toBeTrue();
});

it('rejects tampered payloads', function () {
    $gateway = new FakePaymentGateway('shh');
    $payload = '{"id":"evt_1","type":"payment.succeeded","payment_reference":"PAY-ABCDE12345"}';
    $signature = $gateway->sign($payload);
    $tampered = str_replace('succeeded', 'failed', $payload);

    expect($gateway->verifyWebhookSignature($tampered, $signature))->toBeFalse();
});

it('rejects signatures that do not match the secret', function () {
    $gatewayA = new FakePaymentGateway('secret-a');
    $gatewayB = new FakePaymentGateway('secret-b');
    $payload = '{"id":"evt_1","type":"payment.succeeded","payment_reference":"PAY-X"}';

    expect($gatewayB->verifyWebhookSignature($payload, $gatewayA->sign($payload)))->toBeFalse();
});

it('parses a well-formed webhook payload into a WebhookEvent', function () {
    $gateway = new FakePaymentGateway('shh');
    $payload = json_encode([
        'id' => 'evt_123',
        'type' => 'payment.succeeded',
        'payment_reference' => 'PAY-ABC',
        'data' => ['receipt_url' => 'https://x'],
    ]);

    $event = $gateway->parseWebhookEvent((string) $payload);

    expect($event->providerEventId)->toBe('evt_123')
        ->and($event->type)->toBe('payment.succeeded')
        ->and($event->paymentReference)->toBe('PAY-ABC')
        ->and($event->data)->toBe(['receipt_url' => 'https://x']);
});

it('throws on malformed JSON payloads', function () {
    (new FakePaymentGateway('shh'))->parseWebhookEvent('not-json');
})->throws(InvalidArgumentException::class);

it('throws when a required field is missing', function () {
    (new FakePaymentGateway('shh'))->parseWebhookEvent(json_encode(['id' => 'evt_1']) ?: '');
})->throws(InvalidArgumentException::class);

it('rejects refund attempts that exceed the remaining refundable amount', function () {
    $gateway = new FakePaymentGateway('shh');
    $payment = new class extends Payment
    {
        public function remainingRefundableMinor(): int
        {
            return 1000;
        }
    };

    $result = $gateway->processRefund($payment, 2000);

    expect($result->succeeded())->toBeFalse();
});
