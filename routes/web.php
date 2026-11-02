<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Organizer\CheckInController as OrganizerCheckInController;
use App\Http\Controllers\Organizer\EventController as OrganizerEventController;
use App\Http\Controllers\Organizer\ExportController as OrganizerExportController;
use App\Http\Controllers\Organizer\TicketTypeController as OrganizerTicketTypeController;
use App\Http\Controllers\PaymentSandboxController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('payments.webhook');

Route::get('events', [PublicEventController::class, 'index'])->name('events.index');
Route::get('events/{organizer:slug}/{event:slug}', [PublicEventController::class, 'show'])
    ->scopeBindings()
    ->name('events.show');

// Public receipt verification — shows only {reference, event title, issued status}, no PII.
Route::get('verify/receipts/{publicNumber}', [ReceiptController::class, 'verify'])
    ->name('receipts.verify');

// Guest registration — no auth; creates an attendee account on the fly.
Route::post('events/{organizer:slug}/{event:slug}/guest-register', [RegistrationController::class, 'guestStore'])
    ->scopeBindings()
    ->middleware('throttle:10,1')
    ->name('events.guest-register');

// Auth required but email verification not — attendees who guest-registered
// are logged in but still unverified when they hit the confirmation page.
Route::middleware(['auth'])->group(function () {
    Route::post('events/{organizer:slug}/{event:slug}/register', [RegistrationController::class, 'store'])
        ->scopeBindings()
        ->middleware('throttle:30,1')
        ->name('events.register');
    Route::get('events/{organizer:slug}/{event:slug}/confirmation/{reference}', [RegistrationController::class, 'confirmation'])
        ->scopeBindings()
        ->name('events.registration.confirmation');

    Route::get('me/registrations', [RegistrationController::class, 'index'])->name('me.registrations.index');
    Route::get('me/registrations/{reference}', [RegistrationController::class, 'show'])->name('me.registrations.show');
    Route::post('me/registrations/{reference}/cancel', [RegistrationController::class, 'cancel'])->name('me.registrations.cancel');

    Route::get('me/receipts/{publicNumber}', [ReceiptController::class, 'show'])->name('me.receipts.show');
    Route::get('me/receipts/{publicNumber}/download', [ReceiptController::class, 'download'])->name('me.receipts.download');

    Route::get('me/tickets/{publicToken}', [TicketController::class, 'show'])->name('me.tickets.show');
    Route::get('me/tickets/{publicToken}/qr', [TicketController::class, 'qr'])->name('me.tickets.qr');

    Route::get('payments/sandbox/{reference}', [PaymentSandboxController::class, 'show'])->name('payments.sandbox.show');
    Route::post('payments/sandbox/{reference}/complete', [PaymentSandboxController::class, 'complete'])->name('payments.sandbox.complete');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::scopeBindings()->prefix('organizer/{organizer:slug}')->name('organizer.')->group(function () {
        Route::get('events', [OrganizerEventController::class, 'index'])->name('events.index');
        Route::get('events/create', [OrganizerEventController::class, 'create'])->name('events.create');
        Route::post('events', [OrganizerEventController::class, 'store'])->name('events.store');
        Route::get('events/{event:public_id}/edit', [OrganizerEventController::class, 'edit'])->name('events.edit');
        Route::match(['put', 'patch'], 'events/{event:public_id}', [OrganizerEventController::class, 'update'])->name('events.update');
        Route::delete('events/{event:public_id}', [OrganizerEventController::class, 'destroy'])->name('events.destroy');
        Route::post('events/{event:public_id}/publish', [OrganizerEventController::class, 'publish'])->name('events.publish');
        Route::post('events/{event:public_id}/cancel', [OrganizerEventController::class, 'cancel'])->name('events.cancel');
        Route::post('events/{event:public_id}/archive', [OrganizerEventController::class, 'archive'])->name('events.archive');

        Route::get('exports', [OrganizerExportController::class, 'index'])->name('exports.index');
        Route::post('exports/receipt-register', [OrganizerExportController::class, 'storeRegister'])->name('exports.receipt-register');
        Route::post('exports/receipt-zip', [OrganizerExportController::class, 'storeZip'])->name('exports.receipt-zip');
        Route::post('exports/attendees', [OrganizerExportController::class, 'storeAttendees'])->name('exports.attendees');
        Route::post('exports/check-ins', [OrganizerExportController::class, 'storeCheckIns'])->name('exports.check-ins');
        Route::get('exports/{uuid}/download', [OrganizerExportController::class, 'download'])->name('exports.download');

        Route::get('events/{event:public_id}/check-in', [OrganizerCheckInController::class, 'show'])->name('events.check-in.show');
        Route::post('events/{event:public_id}/check-in/scan', [OrganizerCheckInController::class, 'scan'])->name('events.check-in.scan');
        Route::get('events/{event:public_id}/check-in/search', [OrganizerCheckInController::class, 'search'])->name('events.check-in.search');
        Route::post('events/{event:public_id}/check-in/{checkInId}/reverse', [OrganizerCheckInController::class, 'reverse'])->name('events.check-in.reverse');

        Route::get('events/{event:public_id}/ticket-types', [OrganizerTicketTypeController::class, 'index'])->name('events.ticket-types.index');
        Route::post('events/{event:public_id}/ticket-types', [OrganizerTicketTypeController::class, 'store'])->name('events.ticket-types.store');
        Route::patch('events/{event:public_id}/ticket-types/{ticket_type}', [OrganizerTicketTypeController::class, 'update'])->name('events.ticket-types.update');
        Route::delete('events/{event:public_id}/ticket-types/{ticket_type}', [OrganizerTicketTypeController::class, 'destroy'])->name('events.ticket-types.destroy');
    });

    Route::middleware('system-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
        Route::patch('users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::post('users/{user}/memberships', [AdminUserController::class, 'attachOrganizer'])->name('users.memberships.attach');
        Route::delete('users/{user}/memberships/{organizer:slug}', [AdminUserController::class, 'detachOrganizer'])->name('users.memberships.detach');
    });
});

require __DIR__.'/settings.php';
