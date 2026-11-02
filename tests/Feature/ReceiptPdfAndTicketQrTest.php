<?php

use App\Actions\Receipts\IssueReceipt;
use App\Actions\Tickets\IssueTickets;
use App\Jobs\GenerateReceiptPdf;
use App\Jobs\GenerateTicketQrCode;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\ReceiptIssuedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('generates a receipt PDF to private storage when the job runs', function () {
    Storage::fake('local');

    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)
        ->confirmed()
        ->has(RegistrationAttendee::factory()->state(['email' => 'r@x.test']), 'attendees')
        ->create();
    $receipt = app(IssueReceipt::class)($registration);

    GenerateReceiptPdf::dispatchSync($receipt->id);

    $fresh = $receipt->fresh();
    expect($fresh->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($fresh->pdf_path);
    expect(Storage::disk('local')->size($fresh->pdf_path))->toBeGreaterThan(0);
});

it('generates a ticket QR PNG to private storage when the job runs', function () {
    Storage::fake('local');

    $event = Event::factory()->published()->create();
    $ticketType = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticketType)
        ->confirmed()
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create();
    $tickets = app(IssueTickets::class)($registration);

    GenerateTicketQrCode::dispatchSync($tickets[0]->id);

    $fresh = Ticket::query()->find($tickets[0]->id);
    expect($fresh?->qr_image_path)->not->toBeNull();
    Storage::disk('local')->assertExists($fresh->qr_image_path);
});

it('dispatches both jobs + queues the receipt notification after issuance commits', function () {
    Notification::fake();
    Storage::fake('local');

    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)
        ->confirmed()
        ->has(RegistrationAttendee::factory()->state(['email' => 'owner@x.test']), 'attendees')
        ->create();

    $receipt = app(IssueReceipt::class)($registration);
    app(IssueTickets::class)($registration);

    // The sync queue runs jobs inline; by the time we assert, the PDF path and the QR path should be populated.
    expect($receipt->fresh()->pdf_path)->not->toBeNull();
    expect(Ticket::query()->where('registration_id', $registration->id)->first()?->qr_image_path)->not->toBeNull();

    Notification::assertSentOnDemand(ReceiptIssuedNotification::class);
});

it('serves the receipt PDF via the authenticated download endpoint', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)->for($owner)
        ->confirmed()
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create();
    $receipt = app(IssueReceipt::class)($registration);
    GenerateReceiptPdf::dispatchSync($receipt->id);

    $this->actingAs($owner)
        ->get("/me/receipts/{$receipt->public_number}/download")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('refuses to let a stranger download someone else\'s receipt', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $event = Event::factory()->published()->create();
    $ticket = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)->for($owner)
        ->confirmed()
        ->has(RegistrationAttendee::factory(), 'attendees')
        ->create();
    $receipt = app(IssueReceipt::class)($registration);

    $this->actingAs($stranger)
        ->get("/me/receipts/{$receipt->public_number}/download")
        ->assertForbidden();
});

it('shows the public verification page with no personal info', function () {
    $event = Event::factory()->published()->create(['title' => 'Harbor Lights']);
    $ticket = TicketType::factory()->for($event)->free()->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)
        ->confirmed()
        ->has(RegistrationAttendee::factory()->state(['name' => 'Jane Private', 'email' => 'jane@x.test']), 'attendees')
        ->create();
    $receipt = app(IssueReceipt::class)($registration);

    $response = $this->get("/verify/receipts/{$receipt->public_number}")
        ->assertOk();

    // Content should not include attendee PII.
    expect($response->getContent())->not->toContain('Jane Private')
        ->and($response->getContent())->not->toContain('jane@x.test')
        ->and($response->getContent())->toContain('Harbor Lights');
});

it('returns the verification page with a "not found" state for an unknown receipt number', function () {
    $this->get('/verify/receipts/RCPT-DOES-NOT-EXIST')
        ->assertOk();
});
