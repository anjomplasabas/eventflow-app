<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\TicketKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\StoreTicketTypeRequest;
use App\Http\Requests\Organizer\UpdateTicketTypeRequest;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TicketTypeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Organizer $organizer, Event $event): Response
    {
        $this->authorize('viewAny', [TicketType::class, $event]);

        $ticketTypes = $event->ticketTypes()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TicketType $t) => $this->toListItem($t));

        return Inertia::render('organizer/events/ticket-types/index', [
            'organizer' => $organizer->only(['id', 'slug', 'name']),
            'event' => [
                'public_id' => $event->public_id,
                'title' => $event->title,
            ],
            'ticket_types' => $ticketTypes,
            'kinds' => array_map(fn (TicketKind $k) => ['value' => $k->value, 'label' => ucfirst($k->value)], TicketKind::cases()),
        ]);
    }

    public function store(StoreTicketTypeRequest $request, Organizer $organizer, Event $event): RedirectResponse
    {
        $ticketType = $event->ticketTypes()->create($request->validated());
        $this->audit->record('ticket_type.created', $ticketType, after: $ticketType->only([
            'id', 'name', 'kind', 'price_minor', 'currency', 'capacity',
        ]), actor: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket type created.')]);

        return redirect()->route('organizer.events.ticket-types.index', [
            'organizer' => $organizer,
            'event' => $event,
        ]);
    }

    public function update(UpdateTicketTypeRequest $request, Organizer $organizer, Event $event, TicketType $ticketType): RedirectResponse
    {
        $before = $ticketType->only(array_keys($request->validated()));
        $ticketType->fill($request->validated())->save();
        $this->audit->record('ticket_type.updated', $ticketType, before: $before, after: $ticketType->only(array_keys($request->validated())), actor: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket type updated.')]);

        return redirect()->route('organizer.events.ticket-types.index', [
            'organizer' => $organizer,
            'event' => $event,
        ]);
    }

    public function destroy(Organizer $organizer, Event $event, TicketType $ticketType): RedirectResponse
    {
        $this->authorize('delete', $ticketType);

        $this->audit->record('ticket_type.deleted', $ticketType, before: $ticketType->only(['id', 'name']));
        $ticketType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket type removed.')]);

        return redirect()->route('organizer.events.ticket-types.index', [
            'organizer' => $organizer,
            'event' => $event,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(TicketType $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'kind' => $t->kind->value,
            'price_minor' => $t->price_minor,
            'currency' => $t->currency,
            'price_display' => $t->isFree() ? 'Free' : $t->priceMoney()->format(),
            'capacity' => $t->capacity,
            'min_per_order' => $t->min_per_order,
            'max_per_order' => $t->max_per_order,
            'sales_opens_at' => $t->sales_opens_at?->toIso8601String(),
            'sales_closes_at' => $t->sales_closes_at?->toIso8601String(),
            'active' => $t->active,
            'display_order' => $t->display_order,
        ];
    }
}
