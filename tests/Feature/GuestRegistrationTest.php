<?php

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\RegistrationConfirmedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

function publishedFreeEvent(array $overrides = []): array
{
    $event = Event::factory()->published()->create(array_merge([
        'capacity' => 50,
    ], $overrides));
    $ticket = TicketType::factory()->for($event)->free()->create([
        'name' => 'General',
        'capacity' => 20,
        'min_per_order' => 1,
        'max_per_order' => 2,
    ]);

    return [$event, $ticket];
}

it('registers a brand new guest, creates an account, and lands on confirmation', function () {
    Notification::fake();

    [$event, $ticket] = publishedFreeEvent();

    $response = $this->post("/events/{$event->organizer->slug}/{$event->slug}/guest-register", [
        'ticket_type_id' => $ticket->id,
        'quantity' => 1,
        'terms_accepted' => '1',
        'attendee' => [
            'name' => 'Jamie Rivers',
            'email' => 'jamie@example.test',
        ],
    ]);

    $user = User::query()->where('email', 'jamie@example.test')->firstOrFail();
    $registration = Registration::query()->where('user_id', $user->id)->firstOrFail();

    $response->assertRedirect("/events/{$event->organizer->slug}/{$event->slug}/confirmation/{$registration->reference}");
    expect($user->role)->toBe(UserRole::Attendee)
        ->and($user->email_verified_at)->toBeNull()
        ->and($registration->status)->toBe(RegistrationStatus::Confirmed);

    expect(Auth::id())->toBe($user->id);

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentOnDemand(RegistrationConfirmedNotification::class);
});

it('rejects guest registration when the email already belongs to an account', function () {
    [$event, $ticket] = publishedFreeEvent();
    User::factory()->create(['email' => 'taken@example.test']);

    $this->from("/events/{$event->organizer->slug}/{$event->slug}")
        ->post("/events/{$event->organizer->slug}/{$event->slug}/guest-register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => [
                'name' => 'Someone Else',
                'email' => 'taken@example.test',
            ],
        ])
        ->assertSessionHasErrors(['attendee.email']);

    expect(Registration::query()->count())->toBe(0);
});

it('rejects guest registration when terms are not accepted', function () {
    [$event, $ticket] = publishedFreeEvent();

    $this->from("/events/{$event->organizer->slug}/{$event->slug}")
        ->post("/events/{$event->organizer->slug}/{$event->slug}/guest-register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'attendee' => [
                'name' => 'Jamie Rivers',
                'email' => 'jamie@example.test',
            ],
        ])
        ->assertSessionHasErrors('terms_accepted');

    expect(User::query()->where('email', 'jamie@example.test')->exists())->toBeFalse();
});

it('requires the access code for private events via the guest endpoint', function () {
    $event = Event::factory()->published()->private()->create(['capacity' => 50]);
    $ticket = TicketType::factory()->for($event)->free()->create();

    $this->post("/events/{$event->organizer->slug}/{$event->slug}/guest-register", [
        'ticket_type_id' => $ticket->id,
        'quantity' => 1,
        'terms_accepted' => '1',
        'attendee' => [
            'name' => 'Jamie Rivers',
            'email' => 'jamie@example.test',
        ],
    ])
        ->assertRedirect("/events/{$event->organizer->slug}/{$event->slug}")
        ->assertSessionHasErrors('access_code');

    expect(User::query()->where('email', 'jamie@example.test')->exists())->toBeFalse();
});

it('lets a guest view the confirmation page they just landed on without being verified', function () {
    Notification::fake();

    [$event, $ticket] = publishedFreeEvent();

    $this->post("/events/{$event->organizer->slug}/{$event->slug}/guest-register", [
        'ticket_type_id' => $ticket->id,
        'quantity' => 1,
        'terms_accepted' => '1',
        'attendee' => [
            'name' => 'Jamie Rivers',
            'email' => 'jamie@example.test',
        ],
    ]);

    $registration = Registration::query()->first();

    $this->get("/events/{$event->organizer->slug}/{$event->slug}/confirmation/{$registration->reference}")
        ->assertOk();
});

it('rejects a logged-in user hitting the guest endpoint', function () {
    [$event, $ticket] = publishedFreeEvent();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/events/{$event->organizer->slug}/{$event->slug}/guest-register", [
            'ticket_type_id' => $ticket->id,
            'quantity' => 1,
            'terms_accepted' => '1',
            'attendee' => [
                'name' => 'Jamie Rivers',
                'email' => 'jamie@example.test',
            ],
        ])
        ->assertForbidden();
});
