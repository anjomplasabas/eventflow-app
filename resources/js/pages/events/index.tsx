import { Badge } from '@/components/ui/badge';
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
            <Head title="Events" />
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-8 p-6">
                <header className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Upcoming events
                    </h1>
                    <p className="text-muted-foreground">
                        Browse events from organizers across the platform.
                    </p>
                </header>

                {events.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        No public events are available right now. Check back
                        soon.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {events.map((event) => (
                            <Link
                                key={event.public_id}
                                href={`/events/${event.organizer.slug}/${event.slug}`}
                                className="group block overflow-hidden rounded-xl border bg-card transition hover:border-foreground/30 hover:shadow-sm"
                            >
                                {event.cover_image_url ? (
                                    <img
                                        src={event.cover_image_url}
                                        alt=""
                                        className="h-40 w-full object-cover"
                                    />
                                ) : (
                                    <div className="h-40 w-full bg-gradient-to-br from-neutral-200 to-neutral-100 dark:from-neutral-800 dark:to-neutral-900" />
                                )}
                                <div className="space-y-2 p-4">
                                    <div className="flex items-center justify-between gap-2">
                                        <Badge variant="secondary">
                                            {event.category ?? 'Event'}
                                        </Badge>
                                        {event.status !== 'published' && (
                                            <Badge variant="outline">
                                                {event.status}
                                            </Badge>
                                        )}
                                    </div>
                                    <h2 className="text-lg leading-snug font-semibold group-hover:underline">
                                        {event.title}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {formatRange(
                                            event.starts_at,
                                            event.ends_at,
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {event.organizer.name}
                                    </p>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
