<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Registration;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('dashboard', [
            'summary' => $this->summaryFor($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryFor(User $user): array
    {
        $organizerIds = $user->organizers()->pluck('organizers.id')->all();
        $isAdmin = $user->isSystemAdmin();

        if ($organizerIds === [] && ! $isAdmin) {
            return [
                'audience' => 'attendee',
                'metrics' => $this->attendeeMetrics($user),
                'upcoming' => $this->upcomingAttendeeRegistrations($user),
            ];
        }

        $eventQuery = Event::query();
        if (! $isAdmin) {
            $eventQuery->whereIn('organizer_id', $organizerIds);
        }

        $registrationQuery = Registration::query()->whereHas(
            'event',
            fn ($q) => ! $isAdmin ? $q->whereIn('organizer_id', $organizerIds) : null,
        );

        $checkInQuery = CheckIn::query()->whereHas(
            'event',
            fn ($q) => ! $isAdmin ? $q->whereIn('organizer_id', $organizerIds) : null,
        );

        $revenueMinor = (int) Payment::query()
            ->where('status', 'paid')
            ->whereHas('registration.event', function ($q) use ($isAdmin, $organizerIds): void {
                if (! $isAdmin) {
                    $q->whereIn('organizer_id', $organizerIds);
                }
            })
            ->sum('amount_minor');

        $refundsMinor = (int) Receipt::query()
            ->whereIn('status', ['partially_refunded', 'refunded'])
            ->whereHas('registration.event', function ($q) use ($isAdmin, $organizerIds): void {
                if (! $isAdmin) {
                    $q->whereIn('organizer_id', $organizerIds);
                }
            })
            ->sum('total_minor');

        $byStatus = (clone $registrationQuery)
            ->selectRaw('status, SUM(quantity) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'audience' => $isAdmin ? 'admin' : 'organizer',
            'metrics' => [
                'events_total' => (clone $eventQuery)->count(),
                'events_published' => (clone $eventQuery)->where('status', 'published')->count(),
                'registrations_confirmed' => (int) ($byStatus['confirmed'] ?? 0),
                'registrations_pending' => (int) ($byStatus['payment_pending'] ?? 0) + (int) ($byStatus['pending'] ?? 0),
                'registrations_waitlisted' => (int) ($byStatus['waitlisted'] ?? 0),
                'registrations_cancelled' => (int) ($byStatus['cancelled'] ?? 0),
                'checked_in' => (int) ($byStatus['checked_in'] ?? 0),
                'check_ins_recorded' => (clone $checkInQuery)->whereNull('reversed_at')->count(),
                'revenue_display' => Money::of($revenueMinor, 'USD')->format(),
                'refunds_display' => Money::of($refundsMinor, 'USD')->format(),
            ],
            'upcoming' => $this->upcomingOrganizerEvents($eventQuery),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendeeMetrics(User $user): array
    {
        $byStatus = Registration::query()
            ->where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'confirmed' => (int) ($byStatus[RegistrationStatus::Confirmed->value] ?? 0),
            'waitlisted' => (int) ($byStatus[RegistrationStatus::Waitlisted->value] ?? 0),
            'checked_in' => (int) ($byStatus[RegistrationStatus::CheckedIn->value] ?? 0),
            'cancelled' => (int) ($byStatus[RegistrationStatus::Cancelled->value] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcomingAttendeeRegistrations(User $user): array
    {
        return array_values(Registration::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [RegistrationStatus::Confirmed, RegistrationStatus::CheckedIn, RegistrationStatus::PaymentPending])
            ->with('event.organizer')
            ->whereHas('event', fn ($q) => $q->where('starts_at', '>', now()))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn (Registration $r) => [
                'reference' => $r->reference,
                'status' => $r->status->value,
                'event_title' => $r->event->title,
                'organizer_name' => $r->event->organizer->name,
                'starts_at' => $r->event->starts_at->toIso8601String(),
            ])->all());
    }

    /**
     * @param  Builder<Event>  $eventQuery
     * @return list<array<string, mixed>>
     */
    private function upcomingOrganizerEvents($eventQuery): array
    {
        return array_values((clone $eventQuery)
            ->whereIn('status', ['published', 'scheduled', 'sold_out'])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(fn (Event $e) => [
                'public_id' => $e->public_id,
                'title' => $e->title,
                'starts_at' => $e->starts_at->toIso8601String(),
                'capacity' => $e->capacity,
                'status' => $e->status->value,
            ])->all());
    }
}
