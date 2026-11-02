<?php

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\TicketType;
use App\Notifications\EventReminderNotification;
use Illuminate\Support\Facades\Notification;

it('expires payment_pending registrations older than the threshold', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->paid()->create();

    $stale = Registration::factory()->for($event)->for($ticket)->create([
        'status' => RegistrationStatus::PaymentPending,
        'created_at' => now()->subMinutes(45),
    ]);
    $fresh = Registration::factory()->for($event)->for($ticket)->create([
        'status' => RegistrationStatus::PaymentPending,
        'created_at' => now()->subMinutes(5),
    ]);
    $confirmed = Registration::factory()->for($event)->for($ticket)->confirmed()->create([
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('eventflow:expire-pending-registrations', ['--minutes' => 30])
        ->assertSuccessful();

    expect($stale->fresh()->status)->toBe(RegistrationStatus::Expired)
        ->and($fresh->fresh()->status)->toBe(RegistrationStatus::PaymentPending)
        ->and($confirmed->fresh()->status)->toBe(RegistrationStatus::Confirmed);

    expect(AuditLog::query()->where('action', 'registration.expired')->count())->toBe(1);
});

it('is idempotent — rerunning expire does not re-expire the same row', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->paid()->create();
    Registration::factory()->for($event)->for($ticket)->create([
        'status' => RegistrationStatus::PaymentPending,
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('eventflow:expire-pending-registrations', ['--minutes' => 30]);
    $this->artisan('eventflow:expire-pending-registrations', ['--minutes' => 30]);

    expect(AuditLog::query()->where('action', 'registration.expired')->count())->toBe(1);
});

it('queues one reminder per confirmed registration starting within the horizon', function () {
    Notification::fake();

    $soonEvent = Event::factory()->published()->create([
        'starts_at' => now()->addHours(6),
        'ends_at' => now()->addHours(8),
    ]);
    $farEvent = Event::factory()->published()->create([
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(10)->addHours(2),
    ]);
    $soonTicket = TicketType::factory()->for($soonEvent)->free()->create();
    $farTicket = TicketType::factory()->for($farEvent)->free()->create();

    $soonReg = Registration::factory()->for($soonEvent)->for($soonTicket)->confirmed()
        ->has(RegistrationAttendee::factory()->state(['email' => 'soon@example.com']), 'attendees')
        ->create();
    Registration::factory()->for($farEvent)->for($farTicket)->confirmed()
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create();

    $this->artisan('eventflow:send-event-reminders', ['--hours' => 24])->assertSuccessful();

    Notification::assertSentOnDemandTimes(EventReminderNotification::class, 1);
    expect($soonReg->fresh()->reminder_sent_at)->not->toBeNull();
});

it('does not re-queue reminders that have already been sent', function () {
    Notification::fake();

    $event = Event::factory()->published()->create([
        'starts_at' => now()->addHours(3),
        'ends_at' => now()->addHours(5),
    ]);
    $ticket = TicketType::factory()->for($event)->free()->create();
    Registration::factory()->for($event)->for($ticket)->confirmed()
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create(['reminder_sent_at' => now()->subHour()]);

    $this->artisan('eventflow:send-event-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('marks events whose end time has passed as completed', function () {
    $pastPublished = Event::factory()->published()->create([
        'starts_at' => now()->subHours(5),
        'ends_at' => now()->subHour(),
    ]);
    $futureSoldOut = Event::factory()->create([
        'status' => EventStatus::SoldOut,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
    ]);

    $this->artisan('eventflow:mark-events-completed')->assertSuccessful();

    expect($pastPublished->fresh()->status)->toBe(EventStatus::Completed)
        ->and($futureSoldOut->fresh()->status)->toBe(EventStatus::SoldOut);

    expect(AuditLog::query()->where('action', 'event.completed')->exists())->toBeTrue();
});

it('mark-completed command is safe to re-run', function () {
    Event::factory()->published()->create([
        'starts_at' => now()->subHours(5),
        'ends_at' => now()->subHour(),
    ]);

    $this->artisan('eventflow:mark-events-completed');
    $this->artisan('eventflow:mark-events-completed');

    expect(AuditLog::query()->where('action', 'event.completed')->count())->toBe(1);
});
