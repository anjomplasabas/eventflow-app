import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';

type AttendeeMetrics = {
    confirmed: number;
    waitlisted: number;
    checked_in: number;
    cancelled: number;
};

type OrganizerMetrics = {
    events_total: number;
    events_published: number;
    registrations_confirmed: number;
    registrations_pending: number;
    registrations_waitlisted: number;
    registrations_cancelled: number;
    checked_in: number;
    check_ins_recorded: number;
    revenue_display: string;
    refunds_display: string;
};

type UpcomingAttendee = {
    reference: string;
    status: string;
    event_title: string;
    organizer_name: string;
    starts_at: string;
};

type UpcomingOrganizer = {
    public_id: string;
    title: string;
    starts_at: string;
    capacity: number | null;
    status: string;
};

type Summary =
    | {
          audience: 'attendee';
          metrics: AttendeeMetrics;
          upcoming: UpcomingAttendee[];
      }
    | {
          audience: 'organizer' | 'admin';
          metrics: OrganizerMetrics;
          upcoming: UpcomingOrganizer[];
      };

type Props = { summary: Summary };

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString();
}

export default function Dashboard({ summary }: Props) {
    return (
        <>
            <Head title="Dashboard" />
            {summary.audience === 'attendee' ? (
                <AttendeeDashboard
                    metrics={summary.metrics}
                    upcoming={summary.upcoming}
                />
            ) : (
                <OrganizerDashboard
                    metrics={summary.metrics}
                    upcoming={summary.upcoming}
                    audience={summary.audience}
                />
            )}
        </>
    );
}

function AttendeeDashboard({
    metrics,
    upcoming,
}: {
    metrics: AttendeeMetrics;
    upcoming: UpcomingAttendee[];
}) {
    return (
        <div className="flex flex-col gap-6">
            <Heading
                title="Your dashboard"
                description="Your registrations at a glance."
            />
            <section className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatTile
                    label="Confirmed"
                    value={metrics.confirmed}
                    highlight
                />
                <StatTile label="Checked in" value={metrics.checked_in} />
                <StatTile label="Waitlisted" value={metrics.waitlisted} />
                <StatTile label="Cancelled" value={metrics.cancelled} />
            </section>

            <section className="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <h2 className="border-b px-5 py-3 text-sm font-semibold text-muted-foreground">
                    Upcoming
                </h2>
                {upcoming.length === 0 ? (
                    <div className="p-6 text-sm text-muted-foreground">
                        You don't have any upcoming registrations.{' '}
                        <Link
                            href="/events"
                            className="font-medium text-primary hover:underline"
                        >
                            Browse events
                        </Link>
                        .
                    </div>
                ) : (
                    <ul className="divide-y">
                        {upcoming.map((row) => (
                            <li
                                key={row.reference}
                                className="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                            >
                                <div>
                                    <Link
                                        href={`/me/registrations/${row.reference}`}
                                        className="font-medium hover:underline"
                                    >
                                        {row.event_title}
                                    </Link>
                                    <div className="text-xs text-muted-foreground">
                                        {row.organizer_name} ·{' '}
                                        {formatDateTime(row.starts_at)}
                                    </div>
                                </div>
                                <Badge variant="secondary">{row.status}</Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

function OrganizerDashboard({
    metrics,
    upcoming,
    audience,
}: {
    metrics: OrganizerMetrics;
    upcoming: UpcomingOrganizer[];
    audience: 'organizer' | 'admin';
}) {
    return (
        <div className="flex flex-col gap-6">
            <Heading
                title={
                    audience === 'admin'
                        ? 'Admin dashboard'
                        : 'Organizer dashboard'
                }
                description={
                    audience === 'admin'
                        ? 'System-wide metrics.'
                        : 'Metrics across your organizers.'
                }
            />

            <section className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatTile label="Events total" value={metrics.events_total} />
                <StatTile
                    label="Events published"
                    value={metrics.events_published}
                />
                <StatTile
                    label="Confirmed seats"
                    value={metrics.registrations_confirmed}
                    highlight
                />
                <StatTile label="Checked in" value={metrics.checked_in} />
                <StatTile
                    label="Waitlisted"
                    value={metrics.registrations_waitlisted}
                />
                <StatTile
                    label="Pending"
                    value={metrics.registrations_pending}
                />
                <StatTile label="Revenue" value={metrics.revenue_display} />
                <StatTile label="Refunded" value={metrics.refunds_display} />
            </section>

            <section className="overflow-hidden rounded-2xl border border-border/70 bg-card">
                <h2 className="border-b px-5 py-3 text-sm font-semibold text-muted-foreground">
                    Upcoming events
                </h2>
                {upcoming.length === 0 ? (
                    <div className="p-6 text-sm text-muted-foreground">
                        No upcoming events.
                    </div>
                ) : (
                    <ul className="divide-y">
                        {upcoming.map((row) => (
                            <li
                                key={row.public_id}
                                className="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                            >
                                <div>
                                    <div className="font-medium">
                                        {row.title}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {formatDateTime(row.starts_at)}
                                        {row.capacity !== null
                                            ? ` · capacity ${row.capacity}`
                                            : ''}
                                    </div>
                                </div>
                                <Badge variant="secondary">{row.status}</Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

function StatTile({
    label,
    value,
    highlight = false,
}: {
    label: string;
    value: number | string;
    highlight?: boolean;
}) {
    return (
        <div
            className={`rounded-2xl border p-4 ${highlight ? 'border-primary/40 bg-primary/5' : 'border-border/70 bg-card'}`}
        >
            <div className="text-xs text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
    );
}
