<?php

use App\Enums\PaymentStatus;

it('allows pending to transition to paid, failed, or cancelled', function () {
    expect(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Paid))->toBeTrue()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Failed))->toBeTrue()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Cancelled))->toBeTrue();
});

it('refuses pending → refunded (must be paid first)', function () {
    expect(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Refunded))->toBeFalse()
        ->and(PaymentStatus::Pending->canTransitionTo(PaymentStatus::PartiallyRefunded))->toBeFalse();
});

it('allows paid to be partially refunded or fully refunded', function () {
    expect(PaymentStatus::Paid->canTransitionTo(PaymentStatus::PartiallyRefunded))->toBeTrue()
        ->and(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Refunded))->toBeTrue();
});

it('does not let a paid payment be marked failed or cancelled', function () {
    expect(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Failed))->toBeFalse()
        ->and(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Cancelled))->toBeFalse();
});

it('treats failed, cancelled, and refunded as terminal', function () {
    foreach ([PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Refunded] as $terminal) {
        expect($terminal->isTerminal())->toBeTrue()
            ->and($terminal->allowedTransitions())->toBe([]);
    }
});

it('allows partially refunded to progress to further partial refunds or full refund', function () {
    expect(PaymentStatus::PartiallyRefunded->canTransitionTo(PaymentStatus::PartiallyRefunded))->toBeTrue()
        ->and(PaymentStatus::PartiallyRefunded->canTransitionTo(PaymentStatus::Refunded))->toBeTrue();
});
