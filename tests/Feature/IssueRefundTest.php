<?php

use App\Actions\Payments\IssueRefund;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\RefundIssuedNotification;
use DomainException;
use Illuminate\Support\Facades\Notification;

function paidPaymentFixture(int $amount = 5000): Payment
{
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->paid($amount)->create();
    $registration = Registration::factory()->for($event)->for($ticket)->for(User::factory()->create())
        ->confirmed()
        ->has(RegistrationAttendee::factory()->state(['email' => 'buyer@x', 'name' => 'Buyer']), 'attendees')
        ->create();

    return Payment::factory()->for($registration)->paid()->create([
        'amount_minor' => $amount,
        'currency' => 'USD',
    ]);
}

it('issues a full refund and transitions the payment to Refunded', function () {
    Notification::fake();

    $payment = paidPaymentFixture(2500);
    $refund = app(IssueRefund::class)($payment, 2500, 'duplicate charge');

    expect($refund->status)->toBe('succeeded')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->fresh()->remainingRefundableMinor())->toBe(0);

    Notification::assertSentOnDemand(RefundIssuedNotification::class);
    expect(AuditLog::query()->where('action', 'payment.refunded')->exists())->toBeTrue();
});

it('issues a partial refund and leaves the payment in PartiallyRefunded', function () {
    Notification::fake();

    $payment = paidPaymentFixture(2500);
    app(IssueRefund::class)($payment, 1000);

    expect($payment->fresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($payment->fresh()->remainingRefundableMinor())->toBe(1500);
});

it('promotes a partially-refunded payment to Refunded when the remaining amount is refunded', function () {
    Notification::fake();

    $payment = paidPaymentFixture(2500);
    app(IssueRefund::class)($payment, 1000);
    app(IssueRefund::class)($payment, 1500);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(Refund::query()->where('payment_id', $payment->id)->count())->toBe(2);
});

it('rejects over-refunding', function () {
    $payment = paidPaymentFixture(2500);
    app(IssueRefund::class)($payment, 3000);
})->throws(DomainException::class);

it('rejects refunding a non-paid payment', function () {
    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);
    app(IssueRefund::class)($payment, 100);
})->throws(InvalidEventStateTransition::class);
