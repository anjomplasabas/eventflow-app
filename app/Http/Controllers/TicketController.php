<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function show(Request $request, string $publicToken): Response
    {
        $ticket = $this->findOwnedByUser($request, $publicToken);
        $ticket->loadMissing(['registration', 'event', 'ticketType']);

        return Inertia::render('me/tickets/show', [
            'ticket' => [
                'public_token' => $ticket->public_token,
                'status' => $ticket->status,
                'issued_at' => $ticket->issued_at->toIso8601String(),
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'attendee_name' => $ticket->attendee_name,
                'attendee_email' => $ticket->attendee_email,
                'ticket_type' => $ticket->ticketType?->name,
                'event' => [
                    'title' => $ticket->event->title,
                    'starts_at' => $ticket->event->starts_at->toIso8601String(),
                    'timezone' => $ticket->event->timezone,
                ],
                'qr_image_ready' => $ticket->qr_image_path !== null,
            ],
        ]);
    }

    public function qr(Request $request, string $publicToken): StreamedResponse
    {
        $ticket = $this->findOwnedByUser($request, $publicToken);

        abort_unless(
            $ticket->qr_image_path !== null && Storage::disk('local')->exists($ticket->qr_image_path),
            404
        );

        return Storage::disk('local')->download(
            $ticket->qr_image_path,
            "ticket-{$ticket->public_token}.png",
            ['Content-Type' => 'image/png'],
        );
    }

    private function findOwnedByUser(Request $request, string $publicToken): Ticket
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $ticket = Ticket::query()
            ->with('registration')
            ->where('public_token', $publicToken)
            ->firstOrFail();

        if ($ticket->registration->user_id !== $user->id && ! $user->isSystemAdmin()) {
            abort(403);
        }

        return $ticket;
    }
}
