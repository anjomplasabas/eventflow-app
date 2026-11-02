import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Form, Head, Link } from '@inertiajs/react';

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
type EventSummary = {
    public_id: string;
    title: string;
    slug: string;
    starts_at: string;
    ends_at: string;
    timezone: string;
    organizer: { slug: string; name: string };
};

type Props = {
    registration: Registration;
    event: EventSummary;
    can_cancel: boolean;
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

export default function RegistrationShow({
    registration,
    event,
    can_cancel,
}: Props) {
    return (
        <>
            <Head title={`${registration.reference} · ${event.title}`} />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={event.title}
                        description={`Hosted by ${event.organizer.name}`}
                    />
                    <Badge
                        variant={
                            STATUS_VARIANTS[registration.status] ?? 'secondary'
                        }
                    >
                        {registration.status}
                    </Badge>
                </div>

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
                                Starts
                            </dt>
                            <dd className="mt-1">
                                {formatDateTime(event.starts_at)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Ends
                            </dt>
                            <dd className="mt-1">
                                {formatDateTime(event.ends_at)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Timezone
                            </dt>
                            <dd className="mt-1">{event.timezone}</dd>
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

                <div className="flex items-center gap-2">
                    <Button asChild variant="outline">
                        <Link
                            href={`/events/${event.organizer.slug}/${event.slug}`}
                        >
                            Back to event
                        </Link>
                    </Button>
                    {can_cancel && (
                        <Form
                            {...RegistrationController.cancel.form({
                                reference: registration.reference,
                            })}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Cancel registration
                                </Button>
                            )}
                        </Form>
                    )}
                </div>
            </div>
        </>
    );
}

RegistrationShow.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);
