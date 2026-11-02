import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';

type OrganizerSummary = { slug: string; name: string };
type EventSummary = {
    public_id: string;
    title: string;
    slug: string;
    starts_at: string;
    timezone: string;
};

type Attendee = { name: string; email: string; phone: string | null };
type Registration = {
    reference: string;
    status: string;
    quantity: number;
    confirmed_at: string | null;
    cancelled_at: string | null;
    created_at: string | null;
    ticket_type: { name: string; kind: string; price_display: string };
    attendees: Attendee[];
};

type Props = {
    organizer: OrganizerSummary;
    event: EventSummary;
    registration: Registration;
};

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    confirmed: 'default',
    checked_in: 'default',
    pending: 'secondary',
    payment_pending: 'secondary',
    waitlisted: 'secondary',
    cancelled: 'destructive',
    expired: 'outline',
    refunded: 'outline',
};

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

export default function RegistrationConfirmation({
    organizer,
    event,
    registration,
}: Props) {
    return (
        <>
            <Head title={`Confirmation · ${event.title}`} />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-6">
                <header className="space-y-2">
                    <Badge
                        variant={
                            STATUS_VARIANTS[registration.status] ?? 'secondary'
                        }
                    >
                        {registration.status}
                    </Badge>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        You're registered!
                    </h1>
                    <p className="text-muted-foreground">
                        {event.title} · hosted by {organizer.name}
                    </p>
                </header>

                <section className="rounded-xl border p-6">
                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Reference
                            </dt>
                            <dd className="mt-1 font-mono">
                                {registration.reference}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Ticket
                            </dt>
                            <dd className="mt-1">
                                {registration.ticket_type.name} ·{' '}
                                {registration.ticket_type.price_display}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Quantity
                            </dt>
                            <dd className="mt-1">{registration.quantity}</dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Event starts
                            </dt>
                            <dd className="mt-1">
                                {formatDateTime(event.starts_at)}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section className="rounded-xl border p-6">
                    <h2 className="mb-2 font-medium">Attendee</h2>
                    {registration.attendees.map((a) => (
                        <div key={a.email} className="text-sm">
                            <div>{a.name}</div>
                            <div className="text-muted-foreground">
                                {a.email}
                                {a.phone ? ` · ${a.phone}` : ''}
                            </div>
                        </div>
                    ))}
                </section>

                <div className="flex gap-2">
                    <Button asChild>
                        <Link href="/me/registrations">
                            View my registrations
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={`/events/${organizer.slug}/${event.slug}`}>
                            Back to event
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
