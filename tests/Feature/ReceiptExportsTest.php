<?php

use App\Actions\Receipts\IssueReceipt;
use App\Enums\OrganizerMemberRole;
use App\Jobs\GenerateReceiptRegisterExport;
use App\Jobs\GenerateReceiptZipExport;
use App\Models\Event;
use App\Models\Export;
use App\Models\Organizer;
use App\Models\Registration;
use App\Models\RegistrationAttendee;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function orgOwnerFor(Organizer $organizer): User
{
    $user = User::factory()->create();
    $user->organizers()->attach($organizer, [
        'role' => OrganizerMemberRole::Owner->value,
        'invited_at' => now(),
        'accepted_at' => now(),
    ]);

    return $user;
}

function seedReceiptFor(Organizer $organizer, string $attendeeName = 'Jane'): void
{
    $event = Event::factory()->for($organizer)->published()->create();
    $ticket = TicketType::factory()->for($event)->paid(2500)->create();
    $registration = Registration::factory()
        ->for($event)->for($ticket)
        ->confirmed()
        ->has(RegistrationAttendee::factory()->state(['name' => $attendeeName, 'email' => "{$attendeeName}@x.test"]), 'attendees')
        ->create(['quantity' => 1]);
    app(IssueReceipt::class)($registration);
}

it('generates a CSV receipt register containing every receipt for the organizer', function () {
    Storage::fake('local');

    $organizer = Organizer::factory()->create();
    $owner = orgOwnerFor($organizer);
    seedReceiptFor($organizer, 'Alice');
    seedReceiptFor($organizer, 'Bob');
    seedReceiptFor(Organizer::factory()->create(), 'Charlie'); // different organizer — should NOT appear

    $export = Export::query()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'type' => 'receipt_register',
        'expires_at' => now()->addDays(7),
    ]);

    GenerateReceiptRegisterExport::dispatchSync($export->id);

    $fresh = $export->fresh();
    expect($fresh->status)->toBe('completed')
        ->and($fresh->row_count)->toBe(2)
        ->and($fresh->file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($fresh->file_path);
    $csv = Storage::disk('local')->get($fresh->file_path);
    expect($csv)->toContain('Alice')
        ->and($csv)->toContain('Bob')
        ->and($csv)->not->toContain('Charlie');
});

it('generates a ZIP export containing one PDF per receipt', function () {
    Storage::fake('local');

    $organizer = Organizer::factory()->create();
    $owner = orgOwnerFor($organizer);
    seedReceiptFor($organizer, 'Alice');
    seedReceiptFor($organizer, 'Bob');

    $export = Export::query()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'type' => 'receipt_zip',
        'expires_at' => now()->addDays(7),
    ]);

    GenerateReceiptZipExport::dispatchSync($export->id);

    $fresh = $export->fresh();
    expect($fresh->status)->toBe('completed')
        ->and($fresh->row_count)->toBe(2);

    $zipContents = Storage::disk('local')->get($fresh->file_path);
    expect(strlen($zipContents))->toBeGreaterThan(100);
    expect(substr($zipContents, 0, 2))->toBe('PK'); // zip magic
});

it('lets the organizer owner download a completed export', function () {
    Storage::fake('local');

    $organizer = Organizer::factory()->create();
    $owner = orgOwnerFor($organizer);
    seedReceiptFor($organizer);
    $export = Export::query()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'type' => 'receipt_register',
        'expires_at' => now()->addDays(7),
    ]);
    GenerateReceiptRegisterExport::dispatchSync($export->id);

    $this->actingAs($owner)
        ->get("/organizer/{$organizer->slug}/exports/{$export->uuid}/download")
        ->assertOk();
});

it('refuses to let a non-owner download exports', function () {
    Storage::fake('local');

    $organizer = Organizer::factory()->create();
    $stranger = User::factory()->create();
    seedReceiptFor($organizer);
    $export = Export::query()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $stranger->id,
        'type' => 'receipt_register',
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($stranger)
        ->get("/organizer/{$organizer->slug}/exports/{$export->uuid}/download")
        ->assertForbidden();
});

it('returns 410 Gone for an expired export', function () {
    Storage::fake('local');

    $organizer = Organizer::factory()->create();
    $owner = orgOwnerFor($organizer);
    $export = Export::query()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'type' => 'receipt_register',
        'status' => 'completed',
        'file_path' => 'exports/stale.csv',
        'completed_at' => now()->subDays(10),
        'expires_at' => now()->subDay(),
    ]);
    Storage::disk('local')->put('exports/stale.csv', 'anything');

    $this->actingAs($owner)
        ->get("/organizer/{$organizer->slug}/exports/{$export->uuid}/download")
        ->assertStatus(410);
});

it('cleanup command deletes expired files but keeps the audit row', function () {
    Storage::fake('local');

    $organizer = Organizer::factory()->create();
    $owner = orgOwnerFor($organizer);
    $export = Export::query()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'type' => 'receipt_register',
        'status' => 'completed',
        'file_path' => 'exports/old.csv',
        'completed_at' => now()->subDays(20),
        'expires_at' => now()->subDays(10),
    ]);
    Storage::disk('local')->put('exports/old.csv', 'data');

    $this->artisan('eventflow:delete-expired-exports')->assertSuccessful();

    Storage::disk('local')->assertMissing('exports/old.csv');
    $fresh = $export->fresh();
    expect($fresh->file_path)->toBeNull()
        ->and($fresh->status)->toBe('expired');
});
