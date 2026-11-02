<?php

namespace App\Http\Controllers\Organizer;

use App\Actions\Events\ArchiveEvent;
use App\Actions\Events\CancelEvent;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\PublishEvent;
use App\Actions\Events\UpdateEvent;
use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Exceptions\InvalidEventStateTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\StoreEventRequest;
use App\Http\Requests\Organizer\UpdateEventRequest;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Organizer $organizer): Response
    {
        $this->authorize('view', $organizer);

        $events = $organizer->events()
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Event $event) => $this->toListItem($event));

        return Inertia::render('organizer/events/index', [
            'organizer' => $organizer->only(['id', 'slug', 'name']),
            'events' => $events,
        ]);
    }

    public function create(Organizer $organizer): Response
    {
        $this->authorize('createForOrganizer', [Event::class, $organizer]);

        return Inertia::render('organizer/events/create', [
            'organizer' => $organizer->only(['id', 'slug', 'name']),
            'visibilities' => $this->visibilityOptions(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(StoreEventRequest $request, Organizer $organizer, CreateEvent $create): RedirectResponse
    {
        $event = $create($organizer, $request->toEventAttributes(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event created.')]);

        return redirect()->route('organizer.events.edit', ['organizer' => $organizer, 'event' => $event]);
    }

    public function edit(Organizer $organizer, Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('organizer/events/edit', [
            'organizer' => $organizer->only(['id', 'slug', 'name']),
            'event' => $this->toFormPayload($event),
            'visibilities' => $this->visibilityOptions(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateEventRequest $request, Organizer $organizer, Event $event, UpdateEvent $update): RedirectResponse
    {
        $update($event, $request->toEventAttributes(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event updated.')]);

        return redirect()->route('organizer.events.edit', ['organizer' => $organizer, 'event' => $event]);
    }

    public function destroy(Organizer $organizer, Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event archived.')]);

        return redirect()->route('organizer.events.index', $organizer);
    }

    public function publish(Request $request, Organizer $organizer, Event $event, PublishEvent $publish): RedirectResponse
    {
        $this->authorize('publish', $event);

        try {
            $publish($event, $request->user());
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Event published.')]);
        } catch (InvalidEventStateTransition $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.index', $organizer);
    }

    public function cancel(Request $request, Organizer $organizer, Event $event, CancelEvent $cancel): RedirectResponse
    {
        $this->authorize('cancel', $event);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        try {
            $cancel($event, $reason, $request->user());
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Event cancelled.')]);
        } catch (InvalidEventStateTransition $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.index', $organizer);
    }

    public function archive(Request $request, Organizer $organizer, Event $event, ArchiveEvent $archive): RedirectResponse
    {
        $this->authorize('archive', $event);

        try {
            $archive($event, $request->user());
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Event archived.')]);
        } catch (InvalidEventStateTransition $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('organizer.events.index', $organizer);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Event $event): array
    {
        return [
            'public_id' => $event->public_id,
            'title' => $event->title,
            'slug' => $event->slug,
            'status' => $event->status->value,
            'visibility' => $event->visibility->value,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'capacity' => $event->capacity,
            'cover_image_url' => $event->cover_image_path ? Storage::disk('public')->url($event->cover_image_path) : null,
            'allowed_transitions' => array_map(fn (EventStatus $s) => $s->value, $event->status->allowedTransitions()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toFormPayload(Event $event): array
    {
        return array_merge($event->only([
            'public_id', 'slug', 'title', 'description', 'category', 'visibility', 'access_code',
            'timezone', 'capacity', 'per_attendee_limit', 'cancellation_policy', 'attendee_instructions',
        ]), [
            'status' => $event->status->value,
            'visibility' => $event->visibility->value,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'registration_opens_at' => $event->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $event->registration_closes_at?->toIso8601String(),
            'cover_image_url' => $event->cover_image_path ? Storage::disk('public')->url($event->cover_image_path) : null,
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function visibilityOptions(): array
    {
        return array_map(
            fn (EventVisibility $v) => ['value' => $v->value, 'label' => ucfirst($v->value)],
            EventVisibility::cases(),
        );
    }
}
