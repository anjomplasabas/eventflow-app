<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Enums\OrganizerMemberRole;
use App\Enums\TicketKind;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->makeUser('System Administrator', 'admin@eventflow.test', UserRole::SystemAdmin);
        $pulseOwner = $this->makeUser('Alice Harper', 'alice@pulse.test', UserRole::OrganizerOwner);
        $pulseStaff = $this->makeUser('Bob Kim', 'bob@pulse.test', UserRole::OrganizerStaff);
        $northstarOwner = $this->makeUser('Carla Zhou', 'carla@northstar.test', UserRole::OrganizerOwner);
        $this->makeUser('Dana Lee', 'dana@attendee.test', UserRole::Attendee);

        $pulse = Organizer::query()->updateOrCreate(
            ['slug' => 'pulse-collective'],
            [
                'name' => 'Pulse Collective',
                'contact_email' => 'hello@pulse.test',
                'contact_phone' => '+1-555-0142',
            ],
        );

        $northstar = Organizer::query()->updateOrCreate(
            ['slug' => 'northstar-conferences'],
            [
                'name' => 'Northstar Conferences',
                'contact_email' => 'hello@northstar.test',
                'contact_phone' => '+1-555-0188',
            ],
        );

        $this->attach($pulseOwner, $pulse, OrganizerMemberRole::Owner);
        $this->attach($pulseStaff, $pulse, OrganizerMemberRole::Staff);
        $this->attach($northstarOwner, $northstar, OrganizerMemberRole::Owner);

        $this->seedPulseEvents($pulse);
        $this->seedNorthstarEvents($northstar);
    }

    private function makeUser(string $name, string $email, UserRole $role): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => $role,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
    }

    private function attach(User $user, Organizer $organizer, OrganizerMemberRole $role): void
    {
        $user->organizers()->syncWithoutDetaching([
            $organizer->id => [
                'role' => $role->value,
                'invited_at' => now(),
                'accepted_at' => now(),
            ],
        ]);
    }

    private function seedPulseEvents(Organizer $organizer): void
    {
        $pulseCancellation = "Full refund up to 7 days before the event. 50% refund 3–7 days before. No refunds within 72 hours, but tickets are transferable — email us the new attendee's details and we'll move it over.";

        $this->seedEvent($organizer, [
            'slug' => 'harbor-lights-festival',
            'title' => 'Harbor Lights Festival 2027',
            'category' => 'Festival',
            'description' => <<<'TEXT'
Three days. Two stages. 24 artists. Harbor Lights returns to the waterfront with the biggest lineup we've ever programmed, and a new sunset DJ platform right on the pier.

**Day One · Electronic Night** — Opening sets at 4 PM lean deep and melodic; headline block runs 9–11 PM; late-night DJ sets on the pier until 2 AM.

**Day Two · Live Bands** — Six acts across both stages, including three touring internationals we've been chasing for two years. Food market opens at 2 PM.

**Day Three · Community Day** — Free kids area until 2 PM, intimate acoustic sets through the afternoon, closing DJ set as the sun goes down over the water.
TEXT,
            'attendee_instructions' => 'Gates open at 2 PM each day. Mobile tickets work — screenshots are fine. Reusable water bottles welcome; free refill stations at every stage. No outside alcohol or glass. The beer garden is 21+ with ID. Grab the Pulse app for stage times and food map.',
            'cancellation_policy' => $pulseCancellation,
            'status' => EventStatus::Published,
            'starts' => now()->addDays(45),
            'duration_hours' => 72,
            'capacity' => 4000,
            'tickets' => [
                ['name' => 'Early Bird GA', 'kind' => TicketKind::Paid, 'price' => 7500, 'capacity' => 500, 'display_order' => 0],
                ['name' => 'General Admission', 'kind' => TicketKind::Paid, 'price' => 12500, 'capacity' => 3000, 'display_order' => 1],
                ['name' => 'VIP · Backstage access', 'kind' => TicketKind::Paid, 'price' => 32500, 'capacity' => 150, 'display_order' => 2],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'midnight-sessions-october',
            'title' => 'Midnight Sessions · October',
            'category' => 'Concert',
            'description' => <<<'TEXT'
A late-night listening room with a rotating lineup of ambient and electronic artists. The room is small and the sound system is big.

**Lineup**
• 10:00 PM — Lin Noor (ambient, Berlin)
• 11:15 PM — DJ Myrrh (downtempo)
• 12:30 AM — Fjord & Static (live electronic duo)

Full bar and small plates from the kitchen next door. Doors at 10 PM; last call at 1:45 AM.
TEXT,
            'attendee_instructions' => "21+ with valid ID. Doors at 10 PM sharp — late entry at the staff's discretion. Phones away during sets, we're serious about this. Coat check available ($2).",
            'cancellation_policy' => $pulseCancellation,
            'status' => EventStatus::SoldOut,
            'starts' => now()->addDays(14),
            'duration_hours' => 4,
            'capacity' => 180,
            'tickets' => [
                ['name' => 'General Admission', 'kind' => TicketKind::Paid, 'price' => 3500, 'capacity' => 180, 'display_order' => 0],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'acoustic-garden-series',
            'title' => 'Acoustic Garden Series · Autumn Edition',
            'category' => 'Concert',
            'description' => <<<'TEXT'
An intimate afternoon of singer-songwriters in the botanical garden's wisteria pavilion. Four 20-minute sets with short breaks for applause and conversation.

**Set list**
• 3:00 PM — Maya Oren (indie folk)
• 3:40 PM — Theo Alvarez (classical guitar + vocals)
• 4:20 PM — Juno West (soul)
• 5:00 PM — The Linden Trio (chamber folk)

Picnic blankets are provided; cushions available on request.
TEXT,
            'attendee_instructions' => "Meet at the garden's East Gate 15 minutes before start. Light refreshments provided — tea, lemonade, pastries. Children under 10 free with a paying adult (please email us so we can add them to the manifest). Rain plan: indoor conservatory, same time.",
            'cancellation_policy' => $pulseCancellation,
            'status' => EventStatus::Published,
            'starts' => now()->addDays(22),
            'duration_hours' => 3,
            'capacity' => 220,
            'tickets' => [
                ['name' => 'Standard', 'kind' => TicketKind::Paid, 'price' => 2800, 'capacity' => 180, 'display_order' => 0],
                ['name' => 'Garden Member', 'kind' => TicketKind::Paid, 'price' => 1800, 'capacity' => 40, 'display_order' => 1],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'afterglow-rooftop',
            'title' => 'Afterglow · Rooftop Private Preview',
            'category' => 'Private',
            'description' => <<<'TEXT'
Invite-only preview of the Winter Lineup for Pulse members and press. Expect a short set from each of the three artists we're announcing on Monday, plus drinks and a q&a with the booking team.

Dress warm — the rooftop has heaters but it's still outdoors in November.
TEXT,
            'attendee_instructions' => 'Entry via the North St. service entrance; press badges and the access code at the door. Phones allowed but no video of unreleased music, please. Coat check on the mezzanine.',
            'cancellation_policy' => "Reserved seats only. Email us if you can't make it so we can offer the spot to someone on the invite list.",
            'status' => EventStatus::Published,
            'visibility' => EventVisibility::Private,
            'access_code' => 'AFTERGLOW-27',
            'starts' => now()->addDays(9),
            'duration_hours' => 4,
            'capacity' => 80,
            'tickets' => [
                ['name' => 'Guest list', 'kind' => TicketKind::Free, 'price' => 0, 'capacity' => 80, 'display_order' => 0],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'riverside-fest-2026',
            'title' => 'Riverside Fest 2026',
            'category' => 'Festival',
            'description' => <<<'TEXT'
Last summer's two-day riverside festival sold out at 3,200 attendees. Fourteen artists across two stages, a mile-long food market, and the first-ever Pulse artisan village.

Highlights from last year: the surprise sunset B2B set, the rain delay dance party, and the floating stage experiment that we're definitely bringing back.

Full recap gallery and the live recordings are up on the Pulse Members portal.
TEXT,
            'attendee_instructions' => null,
            'cancellation_policy' => null,
            'status' => EventStatus::Completed,
            'starts' => now()->subMonths(5),
            'duration_hours' => 48,
            'capacity' => 3200,
            'tickets' => [
                ['name' => 'General Admission', 'kind' => TicketKind::Paid, 'price' => 9500, 'capacity' => 3000, 'display_order' => 0],
                ['name' => 'VIP', 'kind' => TicketKind::Paid, 'price' => 24500, 'capacity' => 200, 'display_order' => 1],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'autumn-beats-showcase',
            'title' => 'Autumn Beats Showcase',
            'category' => 'Concert',
            'description' => <<<'TEXT'
**Cancelled.** The venue flooded after last weekend's storm and the structural assessment won't clear in time. Everyone who booked has been fully refunded and will receive a 20% off code for our next Pulse event by email.

We'll be back in the spring with a bigger lineup and a drier venue. Thanks for understanding.
TEXT,
            'attendee_instructions' => null,
            'cancellation_policy' => "Full refunds issued automatically. If you don't see yours within 5 business days please reach out at hello@pulse.test.",
            'status' => EventStatus::Cancelled,
            'starts' => now()->addDays(5),
            'duration_hours' => 5,
            'capacity' => 350,
            'tickets' => [
                ['name' => 'General Admission', 'kind' => TicketKind::Paid, 'price' => 4500, 'capacity' => 350, 'display_order' => 0],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'winter-solstice-rave',
            'title' => 'Winter Solstice Rave (planning)',
            'category' => 'Festival',
            'description' => 'Draft event — still finalising the lineup and venue. Target: a warehouse space in the warehouse district, late December, 8 PM to late. Keep an eye on this page.',
            'attendee_instructions' => null,
            'cancellation_policy' => null,
            'status' => EventStatus::Draft,
            'starts' => now()->addDays(80),
            'duration_hours' => 8,
            'capacity' => 1200,
            'tickets' => [],
        ]);
    }

    private function seedNorthstarEvents(Organizer $organizer): void
    {
        $northstarCancellation = 'Full refund up to 30 days before the event. 50% refund 14–29 days before. Non-refundable within 14 days of the event, but your seat is transferable to a colleague — email registrations@northstar.test with the swap.';

        $this->seedEvent($organizer, [
            'slug' => 'northstar-summit-2027',
            'title' => 'Northstar Summit 2027',
            'category' => 'Conference',
            'description' => <<<'TEXT'
Our flagship business summit returns with two days of main-stage programming and a closing dinner. We've expanded to three parallel tracks this year based on your feedback.

**Day One**
• 9:00 AM — Opening keynote: the state of operating teams
• 10:15 AM — Track sessions begin (Growth · People · Finance)
• 12:30 PM — Lunch and curated roundtables
• 2:00 PM — Afternoon workshops
• 5:00 PM — Welcome reception on the terrace

**Day Two**
• 9:00 AM — Fireside: scaling from Series B to IPO
• 10:30 AM — Second block of track sessions
• 1:00 PM — Lunch
• 2:30 PM — "Operator interviews" (unscripted conversations with four CEOs)
• 4:30 PM — Closing keynote
• 7:30 PM — Executive dinner (ticketed separately)

Speakers announced on a rolling basis — full agenda goes live four weeks out.
TEXT,
            'attendee_instructions' => 'Registration opens at 8:00 AM both days in the Grand Hall foyer. Please bring photo ID and a copy of your confirmation (digital is fine). Business casual. Lanyards are colour-coded by track — pick one at check-in to help us route questions. Wifi credentials in your welcome packet.',
            'cancellation_policy' => $northstarCancellation,
            'status' => EventStatus::Published,
            'starts' => now()->addDays(60),
            'duration_hours' => 50,
            'capacity' => 1200,
            'tickets' => [
                ['name' => 'Early Bird · Standard', 'kind' => TicketKind::Paid, 'price' => 49900, 'capacity' => 200, 'display_order' => 0],
                ['name' => 'Standard', 'kind' => TicketKind::Paid, 'price' => 79900, 'capacity' => 800, 'display_order' => 1],
                ['name' => 'Executive · includes dinner', 'kind' => TicketKind::Paid, 'price' => 169900, 'capacity' => 150, 'display_order' => 2],
                ['name' => 'Student / Nonprofit', 'kind' => TicketKind::Paid, 'price' => 14900, 'capacity' => 50, 'display_order' => 3],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'ai-builders-day',
            'title' => 'AI Builders Day',
            'category' => 'Conference',
            'description' => <<<'TEXT'
A one-day conference for engineering and product leaders shipping AI features into production. No vendor pitches, no vaporware — every session includes a live demo or a case study with numbers.

**Agenda**
• 9:00 AM — Registration + coffee
• 9:45 AM — Opening: what's actually working in production
• 10:30 AM — Case study: evaluation pipelines that catch regressions
• 11:45 AM — Panel: when to fine-tune vs. prompt vs. retrieve
• 1:00 PM — Lunch (networking tables organised by stack)
• 2:15 PM — Workshop track (add-on ticket) or breakout discussions
• 4:30 PM — Case study: cost control at 10M+ requests/day
• 5:30 PM — Closing + drinks
TEXT,
            'attendee_instructions' => "Doors open at 9 AM. Bring a laptop for the workshop track if you've opted in. Lunch is provided — please note any dietary requirements in your registration (we can still accommodate up to 48 hours before). Live captions available on the main stage.",
            'cancellation_policy' => $northstarCancellation,
            'status' => EventStatus::SoldOut,
            'starts' => now()->addDays(26),
            'duration_hours' => 9,
            'capacity' => 400,
            'tickets' => [
                ['name' => 'Standard', 'kind' => TicketKind::Paid, 'price' => 29900, 'capacity' => 350, 'display_order' => 0],
                ['name' => 'Workshop add-on', 'kind' => TicketKind::Paid, 'price' => 19900, 'capacity' => 50, 'display_order' => 1],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'fintech-founders-breakfast',
            'title' => 'Fintech Founders Breakfast · November',
            'category' => 'Networking',
            'description' => <<<'TEXT'
A small, curated breakfast for early-stage fintech founders. The format is deliberately simple: four facilitated roundtables, 45 minutes each, moving between tables.

**Roundtable topics**
• Finding your first 100 serious users
• Compliance and partner banks for pre-seed teams
• Pricing experiments that didn't blow up the funnel
• Hiring the first non-founder engineer

Facilitators from Stripe, Mercury, Ramp, and Chime. Chatham House rule.
TEXT,
            'attendee_instructions' => "Breakfast served 8:00–8:30 AM; programming starts at 8:30 sharp. Business casual. We're in the private dining room on the 12th floor — the elevator attendant has your name. Please come alone (no co-founders at the same table — we'll pair you up).",
            'cancellation_policy' => "Please let us know by 48 hours before if you can't make it so we can offer the spot to someone on the waitlist. We quietly track no-shows.",
            'status' => EventStatus::Published,
            'starts' => now()->addDays(18),
            'duration_hours' => 2,
            'capacity' => 60,
            'tickets' => [
                ['name' => 'Founder seat', 'kind' => TicketKind::Free, 'price' => 0, 'capacity' => 60, 'display_order' => 0],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'startup-pitch-night-q4',
            'title' => 'Startup Pitch Night · Q4',
            'category' => 'Networking',
            'description' => <<<'TEXT'
Six startups pitching live. Five minutes each, two minutes of panelist Q&A, then the audience votes for a "most compelling" award. Networking drinks + light bites run until 9:30 PM.

**Panel**
• Priya Narayan — Partner, First Ridge Capital
• Marcus Chen — Head of Product, Stripe
• Lena Oyelaran — Founder, Catalyst Coaching

Companies are selected two weeks out from a rolling applicant pool. Investor passes are limited to 20 — you must be writing checks (angel or fund) to qualify.
TEXT,
            'attendee_instructions' => 'Doors at 6:30 PM, pitches start at 7:00 sharp. Soft seating in the front rows is reserved for pitching teams. Investor-pass holders have reserved tables in the back for 1:1s after the main program.',
            'cancellation_policy' => 'Free passes can be cancelled anytime. Paid tickets: full refund up to 24 hours before.',
            'status' => EventStatus::Published,
            'starts' => now()->addDays(12),
            'duration_hours' => 3,
            'capacity' => 220,
            'tickets' => [
                ['name' => 'General Admission', 'kind' => TicketKind::Paid, 'price' => 2500, 'capacity' => 200, 'display_order' => 0],
                ['name' => 'Investor pass', 'kind' => TicketKind::Free, 'price' => 0, 'capacity' => 20, 'display_order' => 1],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'executive-leadership-retreat',
            'title' => 'Executive Leadership Retreat',
            'category' => 'Private',
            'description' => <<<'TEXT'
A closed-door, two-day retreat for a small group of CEOs and chief operators from Series B+ companies. Access is by invitation only.

**Format**
• Day one: three facilitated discussions (90 minutes each) on topics the group selects in advance
• Day one evening: dinner with no agenda, by candlelight
• Day two morning: one-on-one walk-and-talks matched by topic
• Day two afternoon: open space / unconference

Chatham House rule. No recording. Phones in the basket at the door.
TEXT,
            'attendee_instructions' => "Full itinerary and venue details sent via encrypted email the week before. Please arrive by 4 PM on day one. Private transportation from the airport is arranged — you'll get a code when you confirm. Business casual, with a jacket for dinner.",
            'cancellation_policy' => "Reserved seats. Email us as soon as you know if you can't make it so we can offer the spot to someone on the invitation list.",
            'status' => EventStatus::Published,
            'visibility' => EventVisibility::Private,
            'access_code' => 'LEAD-NS27',
            'starts' => now()->addDays(55),
            'duration_hours' => 36,
            'capacity' => 30,
            'tickets' => [
                ['name' => 'Reserved seat', 'kind' => TicketKind::Free, 'price' => 0, 'capacity' => 30, 'display_order' => 0],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'northstar-summit-2026',
            'title' => 'Northstar Summit 2026',
            'category' => 'Conference',
            'description' => <<<'TEXT'
Last year's summit sold out at 1,100 attendees across two days of main-stage programming. The hallway track quietly became the best part — we've expanded dedicated networking space for 2027.

Session recordings are available in the Northstar members portal; summary notes and attendee introductions were sent to the mailing list in October.
TEXT,
            'attendee_instructions' => null,
            'cancellation_policy' => null,
            'status' => EventStatus::Completed,
            'starts' => now()->subMonths(8),
            'duration_hours' => 50,
            'capacity' => 1100,
            'tickets' => [
                ['name' => 'Standard', 'kind' => TicketKind::Paid, 'price' => 69900, 'capacity' => 900, 'display_order' => 0],
                ['name' => 'Executive', 'kind' => TicketKind::Paid, 'price' => 149900, 'capacity' => 200, 'display_order' => 1],
            ],
        ]);

        $this->seedEvent($organizer, [
            'slug' => 'growth-marketing-masterclass',
            'title' => 'Growth Marketing Masterclass',
            'category' => 'Workshop',
            'description' => <<<'TEXT'
**Cancelled.** Our lead instructor was unable to travel. Everyone who booked has been fully refunded by the original payment method within 5 business days.

We'll reschedule the workshop in Q1 of next year with the same curriculum. Watch your email — people who were registered for this session will get first access to the new dates.
TEXT,
            'attendee_instructions' => null,
            'cancellation_policy' => "Full refunds issued automatically. If you don't see yours within 5 business days please reach out at registrations@northstar.test.",
            'status' => EventStatus::Cancelled,
            'starts' => now()->addDays(28),
            'duration_hours' => 6,
            'capacity' => 80,
            'tickets' => [
                ['name' => 'Standard', 'kind' => TicketKind::Paid, 'price' => 34900, 'capacity' => 80, 'display_order' => 0],
            ],
        ]);
    }

    /**
     * @param  array{
     *     slug: string,
     *     title: string,
     *     category: string,
     *     description: string,
     *     attendee_instructions?: string|null,
     *     cancellation_policy?: string|null,
     *     status: EventStatus,
     *     visibility?: EventVisibility,
     *     access_code?: string|null,
     *     starts: CarbonInterface,
     *     duration_hours: int,
     *     capacity: int|null,
     *     tickets: list<array{name: string, kind: TicketKind, price: int, capacity: int|null, display_order: int}>,
     * }  $spec
     */
    private function seedEvent(Organizer $organizer, array $spec): void
    {
        $event = Event::query()->updateOrCreate(
            ['organizer_id' => $organizer->id, 'slug' => $spec['slug']],
            [
                'title' => $spec['title'],
                'description' => $spec['description'],
                'category' => $spec['category'],
                'status' => $spec['status'],
                'visibility' => $spec['visibility'] ?? EventVisibility::Public,
                'access_code' => $spec['access_code'] ?? null,
                'starts_at' => $spec['starts'],
                'ends_at' => $spec['starts']->copy()->addHours($spec['duration_hours']),
                'timezone' => 'America/Los_Angeles',
                'registration_opens_at' => now()->subWeek(),
                'registration_closes_at' => $spec['starts']->copy()->subHour(),
                'capacity' => $spec['capacity'],
                'per_attendee_limit' => 4,
                'waitlist_enabled' => in_array($spec['status'], [EventStatus::Published, EventStatus::SoldOut], strict: true),
                'attendee_instructions' => $spec['attendee_instructions'] ?? null,
                'cancellation_policy' => $spec['cancellation_policy'] ?? null,
            ],
        );

        foreach ($spec['tickets'] as $ticket) {
            TicketType::query()->updateOrCreate(
                ['event_id' => $event->id, 'name' => $ticket['name']],
                [
                    'kind' => $ticket['kind'],
                    'price_minor' => $ticket['price'],
                    'currency' => 'USD',
                    'capacity' => $ticket['capacity'],
                    'min_per_order' => 1,
                    'max_per_order' => 4,
                    'active' => true,
                    'display_order' => $ticket['display_order'],
                ],
            );
        }
    }
}
