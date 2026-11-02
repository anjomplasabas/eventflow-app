import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';

type PublicEvent = {
    public_id: string;
    title: string;
    slug: string;
    description: string | null;
    category: string | null;
    status: string;
    cover_image_url: string | null;
    starts_at: string;
    ends_at: string;
    timezone: string;
    organizer: { slug: string; name: string };
};

type Props = { events: PublicEvent[] };

type StatusFilter = 'all' | 'upcoming' | 'sold_out' | 'completed';

const STATUS_FILTERS: { value: StatusFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'sold_out', label: 'Sold out' },
    { value: 'completed', label: 'Past' },
];

function formatRange(startIso: string, endIso: string): string {
    const start = new Date(startIso);
    const end = new Date(endIso);
    const sameDay = start.toDateString() === end.toDateString();
    const dateFmt: Intl.DateTimeFormatOptions = {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    };
    const timeFmt: Intl.DateTimeFormatOptions = {
        hour: 'numeric',
        minute: '2-digit',
    };
    if (sameDay) {
        return `${start.toLocaleDateString(undefined, dateFmt)} · ${start.toLocaleTimeString(undefined, timeFmt)} – ${end.toLocaleTimeString(undefined, timeFmt)}`;
    }
    return `${start.toLocaleDateString(undefined, dateFmt)} – ${end.toLocaleDateString(undefined, dateFmt)}`;
}

function matchesStatus(event: PublicEvent, status: StatusFilter): boolean {
    if (status === 'all') return true;
    if (status === 'upcoming') {
        return event.status === 'published' || event.status === 'scheduled';
    }
    if (status === 'sold_out') return event.status === 'sold_out';
    if (status === 'completed') return event.status === 'completed';
    return true;
}

export default function PublicEventsIndex({ events }: Props) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState<StatusFilter>('all');
    const [category, setCategory] = useState<string>('all');

    const categories = useMemo(() => {
        const set = new Set<string>();
        for (const event of events) {
            if (event.category) set.add(event.category);
        }
        return ['all', ...Array.from(set).sort()];
    }, [events]);

    const filteredEvents = useMemo(() => {
        const q = query.trim().toLowerCase();
        return events.filter((event) => {
            if (!matchesStatus(event, status)) return false;
            if (category !== 'all' && event.category !== category) return false;
            if (q.length === 0) return true;
            return (
                event.title.toLowerCase().includes(q) ||
                event.organizer.name.toLowerCase().includes(q) ||
                (event.description?.toLowerCase().includes(q) ?? false)
            );
        });
    }, [events, query, status, category]);

    return (
        <>
            <Head title="Events — EventFlow" />
            <section className="flex flex-col items-start gap-6 pt-12 lg:pt-16">
                <span className="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium tracking-wide text-primary uppercase">
                    {events.length} event{events.length === 1 ? '' : 's'} live
                    right now
                </span>
                <h1 className="max-w-3xl text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
                    What's happening on{' '}
                    <span className="bg-gradient-to-br from-primary to-cyan-500 bg-clip-text text-transparent">
                        EventFlow
                    </span>
                </h1>
                <p className="max-w-2xl text-base text-muted-foreground sm:text-lg">
                    Discover upcoming events from organizers across the
                    platform. Register in a tap — capacity, waitlists, and
                    tickets handled for you.
                </p>
            </section>

            <section className="mt-10 flex flex-col gap-4 rounded-2xl border border-border/70 bg-card/60 p-4 backdrop-blur md:flex-row md:items-center">
                <div className="relative flex-1">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden
                    />
                    <Input
                        type="search"
                        placeholder="Search by title, organizer, or description"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        className="pl-9"
                    />
                </div>
                <div className="flex flex-wrap gap-2">
                    {STATUS_FILTERS.map((option) => {
                        const active = status === option.value;
                        return (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => setStatus(option.value)}
                                className={`rounded-full border px-3 py-1 text-xs font-medium transition ${
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background text-muted-foreground hover:border-primary/40 hover:text-foreground'
                                }`}
                            >
                                {option.label}
                            </button>
                        );
                    })}
                </div>
                {categories.length > 1 && (
                    <select
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                        aria-label="Filter by category"
                    >
                        {categories.map((cat) => (
                            <option key={cat} value={cat}>
                                {cat === 'all' ? 'All categories' : cat}
                            </option>
                        ))}
                    </select>
                )}
            </section>

            <section className="mt-8 flex flex-col gap-6">
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        Showing{' '}
                        <span className="font-medium text-foreground">
                            {filteredEvents.length}
                        </span>{' '}
                        of {events.length}
                    </span>
                    {(query || status !== 'all' || category !== 'all') && (
                        <button
                            type="button"
                            onClick={() => {
                                setQuery('');
                                setStatus('all');
                                setCategory('all');
                            }}
                            className="text-xs font-medium text-primary hover:underline"
                        >
                            Clear filters
                        </button>
                    )}
                </div>

                {filteredEvents.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-border/70 bg-card/50 p-12 text-center text-muted-foreground">
                        {events.length === 0
                            ? 'No public events are available right now. Check back soon.'
                            : 'No events match your filters.'}
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {filteredEvents.map((event) => (
                            <EventCard
                                key={event.public_id}
                                event={event}
                                formatRange={formatRange}
                            />
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}

function EventCard({
    event,
    formatRange,
}: {
    event: PublicEvent;
    formatRange: (s: string, e: string) => string;
}) {
    return (
        <Link
            href={`/events/${event.organizer.slug}/${event.slug}`}
            className="group relative flex flex-col overflow-hidden rounded-2xl border border-border/70 bg-card transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5"
        >
            <div className="relative aspect-[16/9] w-full overflow-hidden">
                {event.cover_image_url ? (
                    <img
                        src={event.cover_image_url}
                        alt=""
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                ) : (
                    <div className="relative h-full w-full bg-gradient-to-br from-primary/20 via-cyan-400/15 to-sky-400/20">
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.4),transparent_50%)]" />
                    </div>
                )}
                <div className="absolute top-3 left-3 flex items-center gap-2">
                    <Badge
                        variant="secondary"
                        className="bg-background/85 backdrop-blur"
                    >
                        {event.category ?? 'Event'}
                    </Badge>
                    {event.status !== 'published' && (
                        <Badge
                            variant="outline"
                            className="bg-background/85 backdrop-blur"
                        >
                            {event.status.replace('_', ' ')}
                        </Badge>
                    )}
                </div>
            </div>

            <div className="flex flex-1 flex-col gap-3 p-5">
                <h2 className="text-lg leading-snug font-semibold group-hover:text-primary">
                    {event.title}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {formatRange(event.starts_at, event.ends_at)}
                </p>
                <div className="mt-auto flex items-center justify-between pt-2 text-xs text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {event.organizer.name}
                    </span>
                    <span className="text-primary opacity-0 transition group-hover:opacity-100">
                        View →
                    </span>
                </div>
            </div>
        </Link>
    );
}
