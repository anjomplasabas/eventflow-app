<?php

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\OrganizerMemberRole;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Policies\EventPolicy;

function attachUserToOrganizer(User $user, Organizer $organizer, OrganizerMemberRole $role): void
{
    $user->organizers()->attach($organizer, [
        'role' => $role->value,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);
}

it('allows the public to view a published public event', function () {
    $event = Event::factory()->published()->create();

    expect((new EventPolicy)->view(null, $event))->toBeTrue();
});

it('hides draft events from the public', function () {
    $event = Event::factory()->draft()->create();

    expect((new EventPolicy)->view(null, $event))->toBeFalse();
});

it('hides private events from the public even when published', function () {
    $event = Event::factory()->published()->private()->create();

    expect((new EventPolicy)->view(null, $event))->toBeFalse();
});

it('lets organizer owners view their own draft events', function () {
    $event = Event::factory()->draft()->create();
    $owner = User::factory()->create();
    attachUserToOrganizer($owner, $event->organizer, OrganizerMemberRole::Owner);

    expect($owner->can('view', $event))->toBeTrue();
});

it('forbids organizer A from updating organizer B events', function () {
    $eventB = Event::factory()->create();
    $ownerA = User::factory()->create();
    attachUserToOrganizer($ownerA, Organizer::factory()->create(), OrganizerMemberRole::Owner);

    expect($ownerA->can('update', $eventB))->toBeFalse();
});

it('allows system admins to do anything via before()', function () {
    $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
    $event = Event::factory()->draft()->create();

    expect($admin->can('update', $event))->toBeTrue()
        ->and($admin->can('publish', $event))->toBeTrue()
        ->and($admin->can('cancel', $event))->toBeTrue();
});

it('forbids staff from updating or publishing events', function () {
    $event = Event::factory()->draft()->create();
    $staff = User::factory()->create();
    attachUserToOrganizer($staff, $event->organizer, OrganizerMemberRole::Staff);

    expect($staff->can('update', $event))->toBeFalse()
        ->and($staff->can('publish', $event))->toBeFalse()
        ->and($staff->can('cancel', $event))->toBeFalse();
});

it('allows an organizer owner with a valid access code to see private published events', function () {
    $event = Event::factory()->published()->private()->create();
    $owner = User::factory()->create();
    attachUserToOrganizer($owner, $event->organizer, OrganizerMemberRole::Owner);

    expect($owner->can('view', $event))->toBeTrue();
});

it('treats soft-deleted events as not viewable to the public', function () {
    $event = Event::factory()->published()->create();
    $event->delete();

    expect((new EventPolicy)->view(null, $event->fresh()))->toBeFalse();
});

it('rejects viewing cancelled events for the public', function () {
    $event = Event::factory()->create(['status' => EventStatus::Cancelled, 'visibility' => EventVisibility::Public]);

    expect((new EventPolicy)->view(null, $event))->toBeFalse();
});
