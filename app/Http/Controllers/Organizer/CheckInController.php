<?php

namespace App\Http\Controllers\Organizer;

use App\Actions\CheckIns\CheckInAttendee;
use App\Actions\CheckIns\ReverseCheckIn;
use App\Enums\CheckInMethod;
use App\Enums\RegistrationStatus;
use App\Exceptions\CheckInNotAllowed;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Registration;
use App\Models\Ticket;
use DomainException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    use AuthorizesRequests;

    public function show(Organizer $organizer, Event $event): Response
    {
        $this->authorize('performForEvent', [CheckIn::class, $event]);

        return Inertia::render('organizer/events/check-in/index', [
            'organizer' => $organizer->only(['id', 'slug', 'name']),
            'event' => [
                'public_id' => $event->public_id,
                'title' => $event->title,
                'starts_at' => $event->starts_at->toIso8601String(),
                'capacity' => $event->capacity,
            ],
            'stats' => $this->statsFor($event),
            'recent' => $this->recentCheckIns($event),
        ]);
    }

    public function scan(Request $request, Organizer $organizer, Event $event, CheckInAttendee $checkIn): JsonResponse
    {
        $this->authorize('performForEvent', [CheckIn::class, $event]);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'method' => ['nullable', 'in:qr,manual'],
        ]);

        try {
            $record = $checkIn(
                $event,
                $data['token'],
                $request->user(),
                CheckInMethod::from($data['method'] ?? CheckInMethod::Qr->value),
            );

            return response()->json([
                'outcome' => 'success',
                'check_in' => $this->toCheckInPayload($record),
                'stats' => $this->statsFor($event->fresh() ?? $event),
            ]);
        } catch (CheckInNotAllowed $e) {
            [$outcome, $details] = $this->splitOutcome($e->getMessage());

            return response()->json([
                'outcome' => $outcome,
                'details' => $details,
                'stats' => $this->statsFor($event),
            ], 422);
        }
    }

    public function search(Request $request, Organizer $organizer, Event $event): JsonResponse
    {
        $this->authorize('performForEvent', [CheckIn::class, $event]);

        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [RegistrationStatus::Confirmed, RegistrationStatus::CheckedIn, RegistrationStatus::PaymentPending])
            ->where(function ($q) use ($query): void {
                $q->where('reference', 'like', "%{$query}%")
                    ->orWhereHas('attendees', function ($aq) use ($query): void {
                        $aq->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    });
            })
            ->with(['attendees', 'tickets' => fn ($q) => $q->orderBy('id')])
            ->limit(20)
            ->get();

        $results = [];
        foreach ($registrations as $registration) {
            $attendee = $registration->attendees->first();
            foreach ($registration->tickets as $ticket) {
                $results[] = [
                    'public_token' => $ticket->public_token,
                    'attendee_name' => $attendee?->name,
                    'attendee_email' => $attendee?->email,
                    'registration_reference' => $registration->reference,
                    'checked_in' => $ticket->checked_in_at !== null,
                    'status' => $registration->status->value,
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function reverse(Request $request, Organizer $organizer, Event $event, int $checkInId, ReverseCheckIn $reverse): RedirectResponse
    {
        $this->authorize('reverse', [CheckIn::class, $event]);

        $checkIn = CheckIn::query()
            ->where('event_id', $event->id)
            ->findOrFail($checkInId);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $reverse($checkIn, $request->user(), $data['reason']);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Check-in reversed.')]);
        } catch (DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.check-in.show', [
            'organizer' => $organizer,
            'event' => $event,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statsFor(Event $event): array
    {
        $confirmedQuantity = (int) Registration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [RegistrationStatus::Confirmed, RegistrationStatus::CheckedIn])
            ->sum('quantity');

        $ticketsCount = Ticket::query()->where('event_id', $event->id)->where('status', 'issued')->count();
        $checkedIn = Ticket::query()
            ->where('event_id', $event->id)
            ->whereNotNull('checked_in_at')
            ->count();

        return [
            'confirmed_quantity' => $confirmedQuantity,
            'tickets_total' => $ticketsCount,
            'checked_in' => $checkedIn,
            'capacity' => $event->capacity,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentCheckIns(Event $event): array
    {
        return array_values(CheckIn::query()
            ->where('event_id', $event->id)
            ->with(['ticket', 'registration.attendees', 'staff'])
            ->orderByDesc('scanned_at')
            ->limit(20)
            ->get()
            ->map(fn (CheckIn $c) => $this->toCheckInPayload($c))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function toCheckInPayload(CheckIn $checkIn): array
    {
        $registration = $checkIn->registration;
        $attendee = $registration->attendees->first();

        return [
            'id' => $checkIn->id,
            'ticket_token' => $checkIn->ticket->public_token,
            'attendee_name' => $attendee?->name,
            'attendee_email' => $attendee?->email,
            'registration_reference' => $registration->reference,
            'method' => $checkIn->method->value,
            'scanned_at' => $checkIn->scanned_at->toIso8601String(),
            'staff_name' => $checkIn->staff?->name,
            'reversed' => $checkIn->isReversed(),
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitOutcome(string $message): array
    {
        if (str_contains($message, ':')) {
            [$outcome, $details] = explode(':', $message, 2);

            return [$outcome, $details];
        }

        return [$message, null];
    }
}
