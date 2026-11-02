<?php

use App\Enums\OrganizerMemberRole;
use App\Enums\TicketKind;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use App\Models\User;

function orgOwner(Organizer $organizer): User
{
    $user = User::factory()->create();
    $user->organizers()->attach($organizer, [
        'role' => OrganizerMemberRole::Owner->value,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    return $user;
}

function orgStaff(Organizer $organizer): User
{
    $user = User::factory()->create();
    $user->organizers()->attach($organizer, [
        'role' => OrganizerMemberRole::Staff->value,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    return $user;
}

it('lets an owner list ticket types for their event', function () {
    $organizer = Organizer::factory()->create();
    $owner = orgOwner($organizer);
    $event = Event::factory()->for($organizer)->draft()->create();

    $this->actingAs($owner)
        ->get("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types")
        ->assertOk();
});

it('denies attendees from listing ticket types', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->for($organizer)->create();
    $attendee = User::factory()->create();

    $this->actingAs($attendee)
        ->get("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types")
        ->assertForbidden();
});

it('creates a free ticket type and writes an audit entry', function () {
    $organizer = Organizer::factory()->create();
    $owner = orgOwner($organizer);
    $event = Event::factory()->for($organizer)->create();

    $this->actingAs($owner)
        ->post("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types", [
            'name' => 'General Admission',
            'kind' => TicketKind::Free->value,
            'price_minor' => 0,
            'currency' => 'USD',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 2,
            'active' => true,
            'display_order' => 0,
        ])
        ->assertRedirect("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types");

    expect(TicketType::query()->where('name', 'General Admission')->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'ticket_type.created')->exists())->toBeTrue();
});

it('rejects a free ticket type with a non-zero price', function () {
    $organizer = Organizer::factory()->create();
    $owner = orgOwner($organizer);
    $event = Event::factory()->for($organizer)->create();

    $this->actingAs($owner)
        ->from("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types")
        ->post("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types", [
            'name' => 'Bad Free',
            'kind' => TicketKind::Free->value,
            'price_minor' => 1500,
            'currency' => 'USD',
            'min_per_order' => 1,
            'max_per_order' => 1,
            'active' => true,
            'display_order' => 0,
        ])
        ->assertSessionHasErrors('price_minor');
});

it('rejects max_per_order smaller than min_per_order', function () {
    $organizer = Organizer::factory()->create();
    $owner = orgOwner($organizer);
    $event = Event::factory()->for($organizer)->create();

    $this->actingAs($owner)
        ->from("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types")
        ->post("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types", [
            'name' => 'Mismatch',
            'kind' => TicketKind::Free->value,
            'price_minor' => 0,
            'currency' => 'USD',
            'min_per_order' => 5,
            'max_per_order' => 2,
            'active' => true,
            'display_order' => 0,
        ])
        ->assertSessionHasErrors('max_per_order');
});

it('denies staff from creating ticket types', function () {
    $organizer = Organizer::factory()->create();
    $staff = orgStaff($organizer);
    $event = Event::factory()->for($organizer)->create();

    $this->actingAs($staff)
        ->post("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types", [
            'name' => 'By Staff',
            'kind' => TicketKind::Free->value,
            'price_minor' => 0,
            'currency' => 'USD',
            'min_per_order' => 1,
            'max_per_order' => 1,
            'active' => true,
            'display_order' => 0,
        ])
        ->assertForbidden();
});

it('refuses duplicate names within the same event', function () {
    $organizer = Organizer::factory()->create();
    $owner = orgOwner($organizer);
    $event = Event::factory()->for($organizer)->create();
    TicketType::factory()->for($event)->create(['name' => 'VIP']);

    $this->actingAs($owner)
        ->from("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types")
        ->post("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types", [
            'name' => 'VIP',
            'kind' => TicketKind::Paid->value,
            'price_minor' => 10000,
            'currency' => 'USD',
            'min_per_order' => 1,
            'max_per_order' => 1,
            'active' => true,
            'display_order' => 0,
        ])
        ->assertSessionHasErrors('name');
});

it('updates and deletes a ticket type', function () {
    $organizer = Organizer::factory()->create();
    $owner = orgOwner($organizer);
    $event = Event::factory()->for($organizer)->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'Early', 'price_minor' => 1000, 'kind' => TicketKind::Paid]);

    $this->actingAs($owner)
        ->patch("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types/{$ticketType->id}", [
            'name' => 'Early Bird',
            'price_minor' => 2000,
        ])
        ->assertRedirect();

    expect($ticketType->fresh()->name)->toBe('Early Bird')
        ->and($ticketType->fresh()->price_minor)->toBe(2000);

    $this->actingAs($owner)
        ->delete("/organizer/{$organizer->slug}/events/{$event->public_id}/ticket-types/{$ticketType->id}")
        ->assertRedirect();

    expect($ticketType->fresh()->trashed())->toBeTrue();
});

it('refuses to let organizer A touch organizer B ticket types', function () {
    $orgA = Organizer::factory()->create();
    $ownerA = orgOwner($orgA);
    $orgB = Organizer::factory()->create();
    $eventB = Event::factory()->for($orgB)->create();
    $tt = TicketType::factory()->for($eventB)->create();

    $this->actingAs($ownerA)
        ->patch("/organizer/{$orgB->slug}/events/{$eventB->public_id}/ticket-types/{$tt->id}", [
            'name' => 'Hijacked',
        ])
        ->assertForbidden();
});
