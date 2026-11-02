<?php

use App\Enums\ReceiptStatus;

it('lets an issued receipt be voided, partially refunded, or refunded', function () {
    expect(ReceiptStatus::Issued->canTransitionTo(ReceiptStatus::Voided))->toBeTrue()
        ->and(ReceiptStatus::Issued->canTransitionTo(ReceiptStatus::PartiallyRefunded))->toBeTrue()
        ->and(ReceiptStatus::Issued->canTransitionTo(ReceiptStatus::Refunded))->toBeTrue();
});

it('allows partially-refunded to progress to further partial refunds or full refund', function () {
    expect(ReceiptStatus::PartiallyRefunded->canTransitionTo(ReceiptStatus::PartiallyRefunded))->toBeTrue()
        ->and(ReceiptStatus::PartiallyRefunded->canTransitionTo(ReceiptStatus::Refunded))->toBeTrue()
        ->and(ReceiptStatus::PartiallyRefunded->canTransitionTo(ReceiptStatus::Voided))->toBeTrue();
});

it('treats refunded and voided as terminal', function () {
    foreach ([ReceiptStatus::Refunded, ReceiptStatus::Voided] as $terminal) {
        expect($terminal->isTerminal())->toBeTrue()
            ->and($terminal->allowedTransitions())->toBe([]);
    }
});

it('refuses to let a refunded receipt move back to issued', function () {
    expect(ReceiptStatus::Refunded->canTransitionTo(ReceiptStatus::Issued))->toBeFalse();
});
