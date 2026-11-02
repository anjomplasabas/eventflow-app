import { Badge } from '@/components/ui/badge';
import { PublicShell } from '@/layouts/public-shell';
import { Head, Link } from '@inertiajs/react';

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

export default function PublicEventsIndex({ events }: Props) {
    return (
        <>
            <Head title="Events — EventFlow" />
            <PublicShell>
                <section className="flex flex-col items-start gap-6 pt-12 lg:pt-16">
                    <span className="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium tracking-wide text-primary uppercase">
                        {events.length} event{events.length === 1 ? '' : 's'}{' '}
                        live right now
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

                <section className="mt-12 flex flex-col gap-6">
                    {events.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-border/70 bg-card/50 p-12 text-center text-muted-foreground">
                            No public events are available right now. Check back
                            soon.
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {events.map((event) => (
                                <EventCard
                                    key={event.public_id}
                                    event={event}
                                    formatRange={formatRange}
                                />
                            ))}
                        </div>
                    )}
                </section>
            </PublicShell>
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
