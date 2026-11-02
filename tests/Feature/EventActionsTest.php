<?php

use App\Actions\Events\ArchiveEvent;
use App\Actions\Events\CancelEvent;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\PublishEvent;
use App\Actions\Events\UpdateEvent;
use App\Enums\EventStatus;
use App\Exceptions\InvalidEventStateTransition;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

it('creates an event under an organizer and writes an audit entry', function () {
    $organizer = Organizer::factory()->create();
    $actor = User::factory()->create();

    /** @var CreateEvent $action */
    $action = app(CreateEvent::class);
    $event = $action($organizer, [
        'slug' => 'laravel-day',
        'title' => 'Laravel Day',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addHours(2),
        'timezone' => 'UTC',
    ], $actor);

    expect($event->exists)->toBeTrue()
        ->and($event->organizer_id)->toBe($organizer->id)
        ->and($event->public_id)->not->toBeEmpty();

    $log = AuditLog::query()->where('subject_id', $event->id)->first();
    expect($log?->action)->toBe('event.created')
        ->and($log?->actor_user_id)->toBe($actor->id);
});

it('updates fields and audits the before/after diff', function () {
    $event = Event::factory()->draft()->create(['title' => 'Old']);
    $actor = User::factory()->create();

    /** @var UpdateEvent $action */
    $action = app(UpdateEvent::class);
    $action($event, ['title' => 'New'], $actor);

    $log = AuditLog::query()->where('action', 'event.updated')->firstOrFail();
    expect($log->before)->toMatchArray(['title' => 'Old'])
        ->and($log->after)->toMatchArray(['title' => 'New']);
});

it('publishes a draft event and audits the status change', function () {
    $event = Event::factory()->draft()->create();

    /** @var PublishEvent $action */
    $action = app(PublishEvent::class);
    $result = $action($event);

    expect($result->status)->toBe(EventStatus::Published);

    $log = AuditLog::query()->where('action', 'event.published')->firstOrFail();
    expect($log->before)->toBe(['status' => 'draft'])
        ->and($log->after)->toBe(['status' => 'published']);
});

it('refuses to publish a cancelled event', function () {
    $event = Event::factory()->cancelled()->create();

    /** @var PublishEvent $action */
    $action = app(PublishEvent::class);
    $action($event);
})->throws(InvalidEventStateTransition::class);

it('cancels a published event and records the reason', function () {
    $event = Event::factory()->published()->create();

    /** @var CancelEvent $action */
    $action = app(CancelEvent::class);
    $action($event, 'venue flooded');

    $log = AuditLog::query()->where('action', 'event.cancelled')->firstOrFail();
    expect($log->after)->toMatchArray(['reason' => 'venue flooded']);
});

it('archives a cancelled event but refuses to archive a published event directly? No — archive is allowed from any non-archived state', function () {
    $event = Event::factory()->cancelled()->create();

    /** @var ArchiveEvent $action */
    $action = app(ArchiveEvent::class);
    $action($event);

    expect($event->fresh()->status)->toBe(EventStatus::Archived);
});
