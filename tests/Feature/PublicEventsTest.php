<?php

use App\Enums\EventStatus;
use App\Models\Event;
use Inertia\Testing\AssertableInertia;

it('lists only publicly visible events with public-safe statuses', function () {
    $published = Event::factory()->published()->create();
    Event::factory()->draft()->create();
    Event::factory()->published()->private()->create();
    Event::factory()->create(['status' => EventStatus::Cancelled]);
    Event::factory()->create(['status' => EventStatus::Archived]);

    $this->get('/events')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('events/index')
            ->has('events', 1)
            ->where('events.0.public_id', $published->public_id),
        );
});

it('returns the detail page for a published public event', function () {
    $event = Event::factory()->published()->create();

    $this->get("/events/{$event->organizer->slug}/{$event->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('events/show')
            ->where('requires_access_code', false)
            ->where('event.title', $event->title),
        );
});

it('404s for a draft event detail page', function () {
    $event = Event::factory()->draft()->create();

    $this->get("/events/{$event->organizer->slug}/{$event->slug}")
        ->assertNotFound();
});

it('404s when organizer slug and event slug do not match', function () {
    $eventA = Event::factory()->published()->create();
    $eventB = Event::factory()->published()->create();

    $this->get("/events/{$eventA->organizer->slug}/{$eventB->slug}")
        ->assertNotFound();
});

it('404s for a soft-deleted event', function () {
    $event = Event::factory()->published()->create();
    $event->delete();

    $this->get("/events/{$event->organizer->slug}/{$event->slug}")
        ->assertNotFound();
});

it('gates a private event behind an access code', function () {
    $event = Event::factory()->published()->private()->create();

    $this->get("/events/{$event->organizer->slug}/{$event->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('events/show')
            ->where('requires_access_code', true)
            ->where('code_invalid', false),
        );
});

it('shows invalid-code state when access code is wrong', function () {
    $event = Event::factory()->published()->private()->create();

    $this->get("/events/{$event->organizer->slug}/{$event->slug}?code=wrong")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('events/show')
            ->where('requires_access_code', true)
            ->where('code_invalid', true),
        );
});

it('reveals the detail payload when the access code matches', function () {
    $event = Event::factory()->published()->private()->create();

    $this->get("/events/{$event->organizer->slug}/{$event->slug}?code={$event->access_code}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('events/show')
            ->where('requires_access_code', false)
            ->where('event.title', $event->title),
        );
});
