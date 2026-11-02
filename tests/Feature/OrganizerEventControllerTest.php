<?php

use App\Enums\EventStatus;
use App\Enums\OrganizerMemberRole;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeOwner(Organizer $organizer): User
{
    $user = User::factory()->create();
    $user->organizers()->attach($organizer, [
        'role' => OrganizerMemberRole::Owner->value,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    return $user;
}

it('redirects unauthenticated users away from organizer event index', function () {
    $organizer = Organizer::factory()->create();

    $this->get("/organizer/{$organizer->slug}/events")
        ->assertRedirect('/login');
});

it('denies attendees who do not belong to the organizer', function () {
    $organizer = Organizer::factory()->create();
    $attendee = User::factory()->create();

    $this->actingAs($attendee)
        ->get("/organizer/{$organizer->slug}/events")
        ->assertForbidden();
});

it('lets an owner see the event listing', function () {
    $organizer = Organizer::factory()->create();
    $owner = makeOwner($organizer);
    Event::factory()->for($organizer)->published()->create();

    $this->actingAs($owner)
        ->get("/organizer/{$organizer->slug}/events")
        ->assertOk();
});

it('creates an event via the store endpoint and stores the cover image', function () {
    Storage::fake('public');

    $organizer = Organizer::factory()->create();
    $owner = makeOwner($organizer);
    $file = UploadedFile::fake()->image('cover.jpg', 800, 400);

    $response = $this->actingAs($owner)
        ->post("/organizer/{$organizer->slug}/events", [
            'title' => 'Launch Night',
            'slug' => 'launch-night',
            'description' => 'A fine evening.',
            'cover_image' => $file,
            'visibility' => 'public',
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->addHours(2)->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'per_attendee_limit' => 1,
            'capacity' => 100,
        ]);

    $event = Event::query()->where('slug', 'launch-night')->firstOrFail();
    $response->assertRedirect("/organizer/{$organizer->slug}/events/{$event->public_id}/edit");

    expect($event->organizer_id)->toBe($organizer->id)
        ->and($event->cover_image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($event->cover_image_path);
});

it('rejects cover images that are too large', function () {
    Storage::fake('public');

    $organizer = Organizer::factory()->create();
    $owner = makeOwner($organizer);

    $this->actingAs($owner)
        ->from("/organizer/{$organizer->slug}/events/create")
        ->post("/organizer/{$organizer->slug}/events", [
            'title' => 'Big File',
            'slug' => 'big-file',
            'cover_image' => UploadedFile::fake()->create('huge.jpg', 5000, 'image/jpeg'),
            'visibility' => 'public',
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->addHours(2)->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'per_attendee_limit' => 1,
        ])
        ->assertSessionHasErrors('cover_image');
});

it('refuses to let an owner of organizer A edit an event in organizer B', function () {
    $orgA = Organizer::factory()->create();
    $ownerA = makeOwner($orgA);
    $orgB = Organizer::factory()->create();
    $eventB = Event::factory()->for($orgB)->draft()->create();

    $this->actingAs($ownerA)
        ->patch("/organizer/{$orgB->slug}/events/{$eventB->public_id}", [
            'title' => 'Hijacked',
        ])
        ->assertForbidden();
});

it('publishes an event via the publish endpoint', function () {
    $organizer = Organizer::factory()->create();
    $owner = makeOwner($organizer);
    $event = Event::factory()->for($organizer)->draft()->create();

    $this->actingAs($owner)
        ->post("/organizer/{$organizer->slug}/events/{$event->public_id}/publish")
        ->assertRedirect("/organizer/{$organizer->slug}/events");

    expect($event->fresh()->status)->toBe(EventStatus::Published);

    expect(AuditLog::query()->where('action', 'event.published')->exists())->toBeTrue();
});

it('rejects publishing an already-cancelled event with an error toast', function () {
    $organizer = Organizer::factory()->create();
    $owner = makeOwner($organizer);
    $event = Event::factory()->for($organizer)->cancelled()->create();

    $this->actingAs($owner)
        ->post("/organizer/{$organizer->slug}/events/{$event->public_id}/publish")
        ->assertRedirect();

    expect($event->fresh()->status)->toBe(EventStatus::Cancelled);
});

it('cancels an event via the cancel endpoint', function () {
    $organizer = Organizer::factory()->create();
    $owner = makeOwner($organizer);
    $event = Event::factory()->for($organizer)->published()->create();

    $this->actingAs($owner)
        ->post("/organizer/{$organizer->slug}/events/{$event->public_id}/cancel", [
            'reason' => 'Venue cancelled.',
        ])
        ->assertRedirect("/organizer/{$organizer->slug}/events");

    expect($event->fresh()->status)->toBe(EventStatus::Cancelled);
});

it('soft deletes an event via destroy', function () {
    $organizer = Organizer::factory()->create();
    $owner = makeOwner($organizer);
    $event = Event::factory()->for($organizer)->draft()->create();

    $this->actingAs($owner)
        ->delete("/organizer/{$organizer->slug}/events/{$event->public_id}")
        ->assertRedirect("/organizer/{$organizer->slug}/events");

    expect($event->fresh()->trashed())->toBeTrue();
});

it('rejects duplicate slugs within the same organizer but allows reuse across organizers', function () {
    $orgA = Organizer::factory()->create();
    $ownerA = makeOwner($orgA);
    Event::factory()->for($orgA)->create(['slug' => 'kickoff']);

    $this->actingAs($ownerA)
        ->from("/organizer/{$orgA->slug}/events/create")
        ->post("/organizer/{$orgA->slug}/events", [
            'title' => 'Kickoff #2',
            'slug' => 'kickoff',
            'visibility' => 'public',
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->addHours(2)->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'per_attendee_limit' => 1,
        ])
        ->assertSessionHasErrors('slug');

    $orgB = Organizer::factory()->create();
    $ownerB = makeOwner($orgB);

    $this->actingAs($ownerB)
        ->post("/organizer/{$orgB->slug}/events", [
            'title' => 'Kickoff elsewhere',
            'slug' => 'kickoff',
            'visibility' => 'public',
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->addHours(2)->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'per_attendee_limit' => 1,
        ])
        ->assertSessionDoesntHaveErrors();
});
