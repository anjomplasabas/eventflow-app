<?php

namespace App\Http\Controllers;

use App\Actions\Registrations\CancelRegistration;
use App\Actions\Registrations\CreateRegistration;
use App\Enums\EventVisibility;
use App\Enums\RegistrationStatus;
use App\Exceptions\CapacityExhausted;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InvalidEventStateTransition;
use App\Exceptions\RegistrationNotAllowed;
use App\Http\Requests\StoreRegistrationRequest;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Registration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegistrationController extends Controller
{
    use AuthorizesRequests;

    public function store(
        StoreRegistrationRequest $request,
        Organizer $organizer,
        Event $event,
        CreateRegistration $create,
    ): RedirectResponse {
        if ($event->organizer_id !== $organizer->id) {
            throw new NotFoundHttpException;
        }

        if ($event->visibility === EventVisibility::Private) {
            $code = (string) $request->input('access_code', '');
            if ($code === '' || ! hash_equals((string) $event->access_code, $code)) {
                return redirect()->route('events.show', [
                    'organizer' => $organizer,
                    'event' => $event,
                ])->withErrors(['access_code' => __('Access code is required for this event.')]);
            }
        }

        $ticketType = $request->ticketType();
        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $registration = $create(
                $event,
                $ticketType,
                $user,
                $request->attendee(),
                $request->integer('quantity'),
                $request->input('idempotency_key'),
            );
        } catch (CapacityExhausted|RegistrationNotAllowed|IdempotencyConflict $e) {
            return back()->withErrors(['registration' => $e->getMessage()])->withInput();
        }

        return redirect()->route('events.registration.confirmation', [
            'organizer' => $organizer,
            'event' => $event,
            'reference' => $registration->reference,
        ]);
    }

    public function confirmation(Organizer $organizer, Event $event, string $reference, Request $request): Response
    {
        if ($event->organizer_id !== $organizer->id) {
            throw new NotFoundHttpException;
        }

        $registration = Registration::query()
            ->where('reference', $reference)
            ->where('event_id', $event->id)
            ->firstOrFail();

        if ($registration->user_id !== $request->user()?->id) {
            abort(403);
        }

        $registration->load(['ticketType', 'attendees']);

        return Inertia::render('events/registration-confirmation', [
            'organizer' => $organizer->only(['slug', 'name']),
            'event' => [
                'public_id' => $event->public_id,
                'title' => $event->title,
                'slug' => $event->slug,
                'starts_at' => $event->starts_at->toIso8601String(),
                'timezone' => $event->timezone,
            ],
            'registration' => $this->toRegistrationPayload($registration),
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $registrations = Registration::query()
            ->where('user_id', $user->id)
            ->with(['event:id,public_id,slug,title,starts_at,timezone,organizer_id', 'event.organizer:id,slug,name', 'ticketType:id,name,kind,price_minor,currency'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Registration $r) => $this->toListItem($r));

        return Inertia::render('me/registrations/index', [
            'registrations' => $registrations,
        ]);
    }

    public function show(Request $request, string $reference): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $registration = Registration::query()
            ->where('reference', $reference)
            ->where('user_id', $user->id)
            ->with(['event.organizer', 'ticketType', 'attendees'])
            ->firstOrFail();

        return Inertia::render('me/registrations/show', [
            'registration' => $this->toRegistrationPayload($registration),
            'can_cancel' => $request->user()?->can('cancel', $registration) === true
                && $registration->status->canTransitionTo(RegistrationStatus::Cancelled),
            'event' => [
                'public_id' => $registration->event->public_id,
                'title' => $registration->event->title,
                'slug' => $registration->event->slug,
                'starts_at' => $registration->event->starts_at->toIso8601String(),
                'ends_at' => $registration->event->ends_at->toIso8601String(),
                'timezone' => $registration->event->timezone,
                'organizer' => [
                    'slug' => $registration->event->organizer->slug,
                    'name' => $registration->event->organizer->name,
                ],
            ],
        ]);
    }

    public function cancel(Request $request, string $reference, CancelRegistration $cancel): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $registration = Registration::query()
            ->where('reference', $reference)
            ->firstOrFail();

        $this->authorize('cancel', $registration);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $cancel($registration, $user, $data['reason'] ?? null);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Registration cancelled.')]);
        } catch (InvalidEventStateTransition $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('me.registrations.show', ['reference' => $reference]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRegistrationPayload(Registration $r): array
    {
        return [
            'reference' => $r->reference,
            'status' => $r->status->value,
            'quantity' => $r->quantity,
            'confirmed_at' => $r->confirmed_at?->toIso8601String(),
            'cancelled_at' => $r->cancelled_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
            'ticket_type' => [
                'name' => $r->ticketType->name,
                'kind' => $r->ticketType->kind->value,
                'price_display' => $r->ticketType->isFree() ? 'Free' : $r->ticketType->priceMoney()->format(),
            ],
            'attendees' => $r->attendees->map(fn ($a) => [
                'name' => $a->name,
                'email' => $a->email,
                'phone' => $a->phone,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Registration $r): array
    {
        return [
            'reference' => $r->reference,
            'status' => $r->status->value,
            'quantity' => $r->quantity,
            'created_at' => $r->created_at?->toIso8601String(),
            'event' => [
                'public_id' => $r->event->public_id,
                'title' => $r->event->title,
                'slug' => $r->event->slug,
                'starts_at' => $r->event->starts_at->toIso8601String(),
                'organizer_slug' => $r->event->organizer->slug,
            ],
            'ticket_type' => [
                'name' => $r->ticketType->name,
                'price_display' => $r->ticketType->isFree() ? 'Free' : $r->ticketType->priceMoney()->format(),
            ],
        ];
    }
}
