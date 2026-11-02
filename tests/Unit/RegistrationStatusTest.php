<?php

use App\Enums\RegistrationStatus;

it('allows pending to transition to payment_pending, confirmed, waitlisted, cancelled, or expired', function () {
    expect(RegistrationStatus::Pending->canTransitionTo(RegistrationStatus::PaymentPending))->toBeTrue()
        ->and(RegistrationStatus::Pending->canTransitionTo(RegistrationStatus::Confirmed))->toBeTrue()
        ->and(RegistrationStatus::Pending->canTransitionTo(RegistrationStatus::Waitlisted))->toBeTrue()
        ->and(RegistrationStatus::Pending->canTransitionTo(RegistrationStatus::Cancelled))->toBeTrue()
        ->and(RegistrationStatus::Pending->canTransitionTo(RegistrationStatus::Expired))->toBeTrue();
});

it('refuses pending → refunded or checked_in directly', function () {
    expect(RegistrationStatus::Pending->canTransitionTo(RegistrationStatus::Refunded))->toBeFalse()
        ->and(RegistrationStatus::Pending->canTransitionTo(RegistrationStatus::CheckedIn))->toBeFalse();
});

it('lets payment_pending become confirmed, cancelled, or expired', function () {
    expect(RegistrationStatus::PaymentPending->canTransitionTo(RegistrationStatus::Confirmed))->toBeTrue()
        ->and(RegistrationStatus::PaymentPending->canTransitionTo(RegistrationStatus::Cancelled))->toBeTrue()
        ->and(RegistrationStatus::PaymentPending->canTransitionTo(RegistrationStatus::Expired))->toBeTrue();
});

it('allows confirmed to become checked_in, cancelled, or refunded', function () {
    expect(RegistrationStatus::Confirmed->canTransitionTo(RegistrationStatus::CheckedIn))->toBeTrue()
        ->and(RegistrationStatus::Confirmed->canTransitionTo(RegistrationStatus::Cancelled))->toBeTrue()
        ->and(RegistrationStatus::Confirmed->canTransitionTo(RegistrationStatus::Refunded))->toBeTrue();
});

it('refuses confirmed → pending or payment_pending (no going backwards)', function () {
    expect(RegistrationStatus::Confirmed->canTransitionTo(RegistrationStatus::Pending))->toBeFalse()
        ->and(RegistrationStatus::Confirmed->canTransitionTo(RegistrationStatus::PaymentPending))->toBeFalse();
});

it('allows waitlisted to be promoted or cancelled', function () {
    expect(RegistrationStatus::Waitlisted->canTransitionTo(RegistrationStatus::Pending))->toBeTrue()
        ->and(RegistrationStatus::Waitlisted->canTransitionTo(RegistrationStatus::Confirmed))->toBeTrue()
        ->and(RegistrationStatus::Waitlisted->canTransitionTo(RegistrationStatus::Cancelled))->toBeTrue();
});

it('lets checked_in become refunded', function () {
    expect(RegistrationStatus::CheckedIn->canTransitionTo(RegistrationStatus::Refunded))->toBeTrue();
});

it('treats cancelled, expired, and refunded as terminal with no outgoing transitions', function () {
    foreach ([RegistrationStatus::Cancelled, RegistrationStatus::Expired, RegistrationStatus::Refunded] as $terminal) {
        expect($terminal->isTerminal())->toBeTrue()
            ->and($terminal->allowedTransitions())->toBe([]);
    }
});

it('reports which statuses occupy capacity', function () {
    foreach ([RegistrationStatus::Pending, RegistrationStatus::PaymentPending, RegistrationStatus::Confirmed, RegistrationStatus::CheckedIn] as $occupies) {
        expect($occupies->occupiesCapacity())->toBeTrue();
    }

    foreach ([RegistrationStatus::Waitlisted, RegistrationStatus::Cancelled, RegistrationStatus::Expired, RegistrationStatus::Refunded] as $free) {
        expect($free->occupiesCapacity())->toBeFalse();
    }
});
