<?php

use App\Models\Event;
use App\Models\User;
use App\Services\Audit\AuditLogger;

it('records request metadata when available', function () {
    $event = Event::factory()->create();
    $actor = User::factory()->create();

    $this->actingAs($actor);
    $this->withHeaders(['X-Request-Id' => 'req-abc', 'User-Agent' => 'PestAgent/1.0'])
        ->get('/'); // populate current request + ua context

    /** @var AuditLogger $logger */
    $logger = app(AuditLogger::class);
    $log = $logger->record('event.test', $event, after: ['x' => 1]);

    expect($log->actor_user_id)->toBe($actor->id)
        ->and($log->subject_type)->toBe(Event::class)
        ->and($log->subject_id)->toBe($event->id)
        ->and($log->after)->toBe(['x' => 1]);
});

it('falls back to a null actor when none is authenticated', function () {
    $event = Event::factory()->create();

    /** @var AuditLogger $logger */
    $logger = app(AuditLogger::class);
    $log = $logger->record('event.anon', $event);

    expect($log->actor_user_id)->toBeNull();
});
