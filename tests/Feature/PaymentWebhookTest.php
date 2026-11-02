<?php

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\RegistrationConfirmedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function makePendingPayment(int $amount = 2500): Payment
{
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->paid($amount)->create();
    $user = User::factory()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)->for($user)
        ->has(RegistrationAttendee::factory()->state([
            'name' => 'Jane', 'email' => 'jane@example.com',
        ]), 'attendees')
        ->create(['status' => RegistrationStatus::PaymentPending, 'quantity' => 1]);

    return Payment::factory()->for($registration)->create([
        'amount_minor' => $amount,
        'currency' => 'USD',
    ]);
}

function webhookPayload(Payment $payment, string $type, ?string $eventId = null): string
{
    $payload = [
        'id' => $eventId ?? 'evt_'.Str::uuid()->toString(),
        'type' => $type,
        'payment_reference' => $payment->reference,
        'data' => ['provider_reference' => 'pi_'.Str::lower(Str::random(16))],
    ];

    return (string) json_encode($payload);
}

it('rejects webhook requests with an invalid signature', function () {
    $payment = makePendingPayment();
    $body = webhookPayload($payment, 'payment.succeeded');

    $this->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X-Payment-Signature' => 'nope',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertForbidden();

    expect(PaymentEvent::query()->count())->toBe(0);
});

it('rejects an unknown provider', function () {
    $this->call('POST', '/webhooks/payments/unknown', [], [], [], [
        'HTTP_X-Payment-Signature' => 'anything',
        'CONTENT_TYPE' => 'application/json',
    ], '{}')->assertBadRequest();
});

it('stores a webhook event and queues the job on valid signature', function () {
    Queue::fake();

    $payment = makePendingPayment();
    $gateway = app(PaymentGateway::class);
    $body = webhookPayload($payment, 'payment.succeeded');

    $response = $this->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X-Payment-Signature' => $gateway->verifyWebhookSignature($body, '') ? '' : hash_hmac('sha256', $body, (string) config('services.payments.webhook_secret')),
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertOk()->assertJsonFragment(['status' => 'accepted', 'duplicate' => false]);
    expect(PaymentEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessPaymentWebhook::class);
});

it('is idempotent — the same provider_event_id does not re-queue the job', function () {
    Queue::fake();

    $payment = makePendingPayment();
    $body = webhookPayload($payment, 'payment.succeeded', 'evt_fixed');
    $signature = hash_hmac('sha256', $body, (string) config('services.payments.webhook_secret'));

    $this->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X-Payment-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $this->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X-Payment-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk()->assertJsonFragment(['duplicate' => true]);

    expect(PaymentEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessPaymentWebhook::class, 1);
});

it('marks the payment paid and confirms the registration end-to-end', function () {
    Notification::fake();

    $payment = makePendingPayment();
    $body = webhookPayload($payment, 'payment.succeeded');
    $signature = hash_hmac('sha256', $body, (string) config('services.payments.webhook_secret'));

    $this->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X-Payment-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->registration->status)->toBe(RegistrationStatus::Confirmed);

    Notification::assertSentOnDemand(RegistrationConfirmedNotification::class);
    expect(AuditLog::query()->where('action', 'payment.succeeded')->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'registration.confirmed')->exists())->toBeTrue();
});

it('marks the payment failed and cancels the registration on payment.failed', function () {
    $payment = makePendingPayment();
    $body = webhookPayload($payment, 'payment.failed');
    $signature = hash_hmac('sha256', $body, (string) config('services.payments.webhook_secret'));

    $this->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X-Payment-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->registration->status)->toBe(RegistrationStatus::Cancelled);
});

it('handles an out-of-order replay (failed after already paid) without regressing', function () {
    $payment = makePendingPayment();

    $successBody = webhookPayload($payment, 'payment.succeeded');
    $this->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X-Payment-Signature' => hash_hmac('sha256', $successBody, (string) config('services.payments.webhook_secret')),
        'CONTENT_TYPE' => 'application/json',
    ], $successBody)->assertOk();

    // Stale "failed" event arrives after — must not downgrade a paid payment.
    $failedBody = webhookPayload($payment, 'payment.failed');
    $this->call('POST', '/webhooks/payments/fake', [], [], [], [
        'HTTP_X-Payment-Signature' => hash_hmac('sha256', $failedBody, (string) config('services.payments.webhook_secret')),
        'CONTENT_TYPE' => 'application/json',
    ], $failedBody)->assertOk();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fresh()->registration->status)->toBe(RegistrationStatus::Confirmed);
});

it('redirects paid registrations through the sandbox checkout', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->paid(5000)->create(['capacity' => 10]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $registration = Registration::query()->where('user_id', $user->id)->firstOrFail();
    $payment = Payment::query()->where('registration_id', $registration->id)->firstOrFail();
    $response->assertRedirect(route('payments.sandbox.show', ['reference' => $payment->reference]));

    expect($registration->status)->toBe(RegistrationStatus::PaymentPending)
        ->and($payment->status)->toBe(PaymentStatus::Pending);
});

it('completes the sandbox checkout successfully and lands on the confirmation page', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->paid(5000)->create(['capacity' => 10]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $payment = Payment::query()->where('registration_id', '>', 0)->orderByDesc('id')->firstOrFail();

    $this->actingAs($user)
        ->post("/payments/sandbox/{$payment->reference}/complete", ['outcome' => 'succeed'])
        ->assertRedirect("/events/{$event->organizer->slug}/{$event->slug}/confirmation/{$payment->registration->reference}");

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});
