<?php

use App\Enums\RegistrationStatus;
use App\Enums\TicketKind;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;

function publishedEvent(array $overrides = []): Event
{
    return Event::factory()->published()->create(array_merge([
        'capacity' => 50,
    ], $overrides));
}

function freeTicketFor(Event $event, array $overrides = []): TicketType
{
    return TicketType::factory()->for($event)->free()->create(array_merge([
        'name' => 'GA',
        'capacity' => 10,
        'min_per_order' => 1,
        'max_per_order' => 2,
    ], $overrides));
}

it('redirects guests to login when they submit the registration form', function () {
    $event = publishedEvent();
    $ticket = freeTicketFor($event);

    $this->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
        'ticket_type_id' => $ticket->id,
        'quantity' => 1,
        'terms_accepted' => '1',
        'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
    ])->assertRedirect('/login');
});

it('registers an authenticated user for a free event and redirects to confirmation', function () {
    $event = publishedEvent();
    $ticket = freeTicketFor($event);
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    $registration = Registration::query()->where('user_id', $user->id)->firstOrFail();
    $response->assertRedirect("/events/{$event->organizer->slug}/{$event->slug}/confirmation/{$registration->reference}");

    expect($registration->status)->toBe(RegistrationStatus::Confirmed);
});

it('rejects the form when terms are not accepted', function () {
    $event = publishedEvent();
    $ticket = freeTicketFor($event);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from("/events/{$event->organizer->slug}/{$event->slug}")
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ])->assertSessionHasErrors('terms_accepted');
});

it('404s when organizer slug and event slug do not match', function () {
    $eventA = publishedEvent();
    $eventB = publishedEvent();
    $ticket = freeTicketFor($eventB);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/events/{$eventA->organizer->slug}/{$eventB->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ])->assertNotFound();
});

it('rejects submission with a ticket type belonging to a different event', function () {
    $event = publishedEvent();
    $other = publishedEvent();
    $foreignTicket = freeTicketFor($other);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from("/events/{$event->organizer->slug}/{$event->slug}")
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $foreignTicket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ])->assertSessionHasErrors('ticket_type_id');
});

it('is idempotent when the same key is submitted twice', function () {
    $event = publishedEvent();
    $ticket = freeTicketFor($event);
    $user = User::factory()->create();

    $payload = [
        'ticket_type_id' => $ticket->id,
        'quantity' => 1,
        'terms_accepted' => '1',
        'idempotency_key' => 'client-key-1',
        'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
    ];

    $this->actingAs($user)->post("/events/{$event->organizer->slug}/{$event->slug}/register", $payload);
    $this->actingAs($user)->post("/events/{$event->organizer->slug}/{$event->slug}/register", $payload);

    expect(Registration::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('surfaces a form error when capacity is exhausted', function () {
    $event = publishedEvent();
    $ticket = freeTicketFor($event, ['capacity' => 1]);
    Registration::factory()->for($event)->for($ticket)->confirmed()->create(['quantity' => 1]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from("/events/{$event->organizer->slug}/{$event->slug}")
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ])->assertSessionHasErrors('registration');
});

it('requires access code for private events', function () {
    $event = Event::factory()->published()->private()->create();
    $ticket = freeTicketFor($event);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ])->assertRedirect("/events/{$event->organizer->slug}/{$event->slug}")
        ->assertSessionHasErrors('access_code');
});

it('accepts registration for a private event with the correct access code', function () {
    $event = Event::factory()->published()->private()->create();
    $ticket = freeTicketFor($event);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'access_code' => $event->access_code,
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

    expect(Registration::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('lists only the current user registrations on /me/registrations', function () {
    $event = publishedEvent();
    $ticket = freeTicketFor($event);
    $me = User::factory()->create();
    $other = User::factory()->create();

    Registration::factory()->for($event)->for($ticket)->for($me)->confirmed()->create();
    Registration::factory()->for($event)->for($ticket)->for($other)->confirmed()->create();

    $this->actingAs($me)
        ->get('/me/registrations')
        ->assertOk();

    // Validate scoping by hitting show
    $otherReg = Registration::query()->where('user_id', $other->id)->firstOrFail();
    $this->actingAs($me)
        ->get("/me/registrations/{$otherReg->reference}")
        ->assertNotFound();
});

it('surfaces ticket types in the public event detail payload', function () {
    $event = publishedEvent();
    freeTicketFor($event, ['name' => 'GA', 'display_order' => 1]);
    TicketType::factory()->for($event)->paid(5000)->create([
        'name' => 'VIP',
        'display_order' => 0,
    ]);
    TicketType::factory()->for($event)->free()->create([
        'name' => 'Inactive',
        'active' => false,
    ]);

    $this->get("/events/{$event->organizer->slug}/{$event->slug}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('events/show')
            ->has('event.ticket_types', 2)
            ->where('event.ticket_types.0.name', 'VIP')
            ->where('event.ticket_types.1.name', 'GA'),
        );
});

it('shows the confirmation page only to the registration owner', function () {
    $event = publishedEvent();
    $ticket = freeTicketFor($event);
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $reg = Registration::factory()->for($event)->for($ticket)->for($owner)->confirmed()->create();

    $this->actingAs($owner)
        ->get("/events/{$event->organizer->slug}/{$event->slug}/confirmation/{$reg->reference}")
        ->assertOk();

    $this->actingAs($stranger)
        ->get("/events/{$event->organizer->slug}/{$event->slug}/confirmation/{$reg->reference}")
        ->assertForbidden();
});

it('expects quantity to respect ticket type min/max', function () {
    $event = publishedEvent();
    $ticket = freeTicketFor($event, ['min_per_order' => 1, 'max_per_order' => 2]);
    $user = User::factory()->create();

    expect($ticket->kind)->toBe(TicketKind::Free);

    $this->actingAs($user)
        ->from("/events/{$event->organizer->slug}/{$event->slug}")
        ->post("/events/{$event->organizer->slug}/{$event->slug}/register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 5,
            'terms_accepted' => '1',
            'attendee' => ['name' => 'Jane', 'email' => 'jane@example.com'],
        ])->assertSessionHasErrors('registration');
});
