<?php

use App\Actions\Payments\RecordPaymentSucceeded;
use App\Actions\Receipts\IssueReceipt;
use App\Actions\Registrations\CreateRegistration;
use App\Actions\Tickets\IssueTickets;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;

it('issues a receipt and tickets after a free registration is confirmed', function () {
    $event = Event::factory()->published()->create(['capacity' => 50]);
    $ticket = TicketType::factory()->for($event)->free()->create([
        'capacity' => 10, 'max_per_order' => 4,
    ]);
    $user = User::factory()->create();

    $registration = app(CreateRegistration::class)(
        $event,
        $ticket,
        $user,
        ['name' => 'Jamie', 'email' => 'jamie@example.test'],
        quantity: 2,
    );

    expect($registration->status)->toBe(RegistrationStatus::Confirmed);

    $receipt = Receipt::query()->where('registration_id', $registration->id)->firstOrFail();
    expect($receipt->status)->toBe(ReceiptStatus::Issued)
        ->and($receipt->total_minor)->toBe(0)
        ->and($receipt->items)->toHaveCount(1)
        ->and($receipt->items->first()->quantity)->toBe(2)
        ->and($receipt->public_number)->toStartWith('RCPT-');

    $tickets = Ticket::query()->where('registration_id', $registration->id)->get();
    expect($tickets)->toHaveCount(2);
    foreach ($tickets as $t) {
        expect(strlen($t->public_token))->toBe(32)
            ->and($t->status)->toBe('issued');
    }
});

it('issues a receipt + tickets after a paid webhook confirms the payment', function () {
    $event = Event::factory()->published()->create(['capacity' => 50]);
    $ticket = TicketType::factory()->for($event)->paid(2500)->create(['capacity' => 10, 'max_per_order' => 4]);
    $user = User::factory()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)->for($user)
        ->has(RegistrationAttendee::factory()->state(['name' => 'Jamie', 'email' => 'jamie@example.test']), 'attendees')
        ->create(['status' => RegistrationStatus::PaymentPending, 'quantity' => 2]);
    $payment = Payment::factory()->for($registration)->create([
        'amount_minor' => 5000, 'currency' => 'USD',
    ]);

    app(RecordPaymentSucceeded::class)($payment);

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Confirmed)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);

    $receipt = Receipt::query()->where('registration_id', $registration->id)->firstOrFail();
    expect($receipt->payment_id)->toBe($payment->id)
        ->and($receipt->total_minor)->toBe(5000)
        ->and($receipt->currency)->toBe('USD');

    expect(Ticket::query()->where('registration_id', $registration->id)->count())->toBe(2);
});

it('is idempotent — the same registration never gets a second receipt or extra tickets', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create(['max_per_order' => 4]);
    $registration = Registration::factory()
        ->for($event)->for($ticket)
        ->confirmed()
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create(['quantity' => 2]);

    app(IssueReceipt::class)($registration);
    app(IssueReceipt::class)($registration);
    app(IssueTickets::class)($registration);
    app(IssueTickets::class)($registration);

    expect(Receipt::query()->where('registration_id', $registration->id)->count())->toBe(1)
        ->and(Ticket::query()->where('registration_id', $registration->id)->count())->toBe(2);
});

it('snapshots the organizer + event state at issue time — later edits do not touch the receipt', function () {
    $event = Event::factory()->published()->create(['title' => 'Original Title']);
    $ticket = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)
        ->confirmed()
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create();

    $receipt = app(IssueReceipt::class)($registration);
    $snapshotTitle = $receipt->event_snapshot['title'];

    $event->update(['title' => 'Changed Afterwards']);
    $event->organizer->update(['name' => 'Renamed Org']);

    $fresh = $receipt->fresh();
    expect($fresh->event_snapshot['title'])->toBe($snapshotTitle)
        ->and($fresh->event_snapshot['title'])->toBe('Original Title');
});

it('generates opaque ticket tokens that are not sequential ids', function () {
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create(['max_per_order' => 4]);
    $registration = Registration::factory()
        ->for($event)->for($ticket)
        ->confirmed()
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create(['quantity' => 3]);

    $tickets = app(IssueTickets::class)($registration);
    $tokens = array_map(fn ($t) => $t->public_token, $tickets);

    expect($tokens)->toHaveCount(3);
    foreach ($tokens as $token) {
        expect(strlen($token))->toBe(32)
            ->and(preg_match('/^[a-z0-9]+$/', $token))->toBe(1);
    }
    // All distinct
    expect(array_unique($tokens))->toHaveCount(3);
});
