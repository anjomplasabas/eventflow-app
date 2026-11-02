<?php

use App\Enums\EventStatus;

it('allows draft to progress to scheduled, published, cancelled, or archived', function () {
    expect(EventStatus::Draft->canTransitionTo(EventStatus::Scheduled))->toBeTrue()
        ->and(EventStatus::Draft->canTransitionTo(EventStatus::Published))->toBeTrue()
        ->and(EventStatus::Draft->canTransitionTo(EventStatus::Cancelled))->toBeTrue()
        ->and(EventStatus::Draft->canTransitionTo(EventStatus::Archived))->toBeTrue();
});

it('refuses to transition draft directly to completed or sold_out', function () {
    expect(EventStatus::Draft->canTransitionTo(EventStatus::Completed))->toBeFalse()
        ->and(EventStatus::Draft->canTransitionTo(EventStatus::SoldOut))->toBeFalse();
});

it('allows published to become sold_out, completed, or cancelled', function () {
    expect(EventStatus::Published->canTransitionTo(EventStatus::SoldOut))->toBeTrue()
        ->and(EventStatus::Published->canTransitionTo(EventStatus::Completed))->toBeTrue()
        ->and(EventStatus::Published->canTransitionTo(EventStatus::Cancelled))->toBeTrue();
});

it('allows sold_out to reopen to published when capacity is released', function () {
    expect(EventStatus::SoldOut->canTransitionTo(EventStatus::Published))->toBeTrue();
});

it('treats archived as a terminal state', function () {
    expect(EventStatus::Archived->isTerminal())->toBeTrue()
        ->and(EventStatus::Archived->allowedTransitions())->toBe([]);
});

it('forbids transitions out of cancelled except archival', function () {
    expect(EventStatus::Cancelled->canTransitionTo(EventStatus::Published))->toBeFalse()
        ->and(EventStatus::Cancelled->canTransitionTo(EventStatus::Archived))->toBeTrue();
});

it('forbids transitions out of completed except archival', function () {
    expect(EventStatus::Completed->canTransitionTo(EventStatus::Published))->toBeFalse()
        ->and(EventStatus::Completed->canTransitionTo(EventStatus::Archived))->toBeTrue();
});
