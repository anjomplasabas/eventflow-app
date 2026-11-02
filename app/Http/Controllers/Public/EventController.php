<?php

namespace App\Http\Controllers\Public;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventController extends Controller
{
    private const PUBLIC_STATUSES = [
        EventStatus::Scheduled,
        EventStatus::Published,
        EventStatus::SoldOut,
        EventStatus::Completed,
    ];

    public function index(): Response
    {
        $events = Event::query()
            ->with('organizer:id,slug,name')
            ->where('visibility', EventVisibility::Public)
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->orderBy('starts_at')
            ->limit(60)
            ->get()
            ->map(fn (Event $event) => $this->toListItem($event));

        return Inertia::render('events/index', [
            'events' => $events,
        ]);
    }

    public function show(Request $request, Organizer $organizer, Event $event): Response
    {
        if ($event->organizer_id !== $organizer->id || $event->trashed()) {
            throw new NotFoundHttpException;
        }

        if (! in_array($event->status, self::PUBLIC_STATUSES, strict: true)) {
            throw new NotFoundHttpException;
        }

        if ($event->visibility === EventVisibility::Private) {
            $providedCode = (string) $request->query('code', '');
            if ($providedCode === '' || ! hash_equals((string) $event->access_code, $providedCode)) {
                return Inertia::render('events/show', [
                    'event' => $this->toTeaser($event),
                    'organizer' => $this->toOrganizer($organizer),
                    'requires_access_code' => true,
                    'code_invalid' => $providedCode !== '',
                ]);
            }
        }

        return Inertia::render('events/show', [
            'event' => $this->toDetail($event),
            'organizer' => $this->toOrganizer($organizer),
            'requires_access_code' => false,
            'code_invalid' => false,
        ]);
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
            'description' => $event->description,
            'category' => $event->category,
            'status' => $event->status->value,
            'cover_image_url' => $event->cover_image_path ? Storage::disk('public')->url($event->cover_image_path) : null,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'timezone' => $event->timezone,
            'organizer' => [
                'slug' => $event->organizer->slug,
                'name' => $event->organizer->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toTeaser(Event $event): array
    {
        return [
            'public_id' => $event->public_id,
            'title' => $event->title,
            'slug' => $event->slug,
            'cover_image_url' => $event->cover_image_path ? Storage::disk('public')->url($event->cover_image_path) : null,
            'starts_at' => $event->starts_at->toIso8601String(),
            'timezone' => $event->timezone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(Event $event): array
    {
        $ticketTypes = $event->ticketTypes()
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('sales_opens_at')->orWhere('sales_opens_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('sales_closes_at')->orWhere('sales_closes_at', '>=', now());
            })
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TicketType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'kind' => $t->kind->value,
                'price_display' => $t->isFree() ? 'Free' : $t->priceMoney()->format(),
                'min_per_order' => $t->min_per_order,
                'max_per_order' => $t->max_per_order,
            ])
            ->values();

        return [
            'public_id' => $event->public_id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'category' => $event->category,
            'cover_image_url' => $event->cover_image_path ? Storage::disk('public')->url($event->cover_image_path) : null,
            'status' => $event->status->value,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'timezone' => $event->timezone,
            'registration_opens_at' => $event->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $event->registration_closes_at?->toIso8601String(),
            'capacity' => $event->capacity,
            'attendee_instructions' => $event->attendee_instructions,
            'cancellation_policy' => $event->cancellation_policy,
            'ticket_types' => $ticketTypes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toOrganizer(Organizer $organizer): array
    {
        return [
            'slug' => $organizer->slug,
            'name' => $organizer->name,
            'contact_email' => $organizer->contact_email,
        ];
    }
}
