import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

type Row = {
    reference: string;
    status: string;
    quantity: number;
    created_at: string | null;
    event: {
        public_id: string;
        title: string;
        slug: string;
        starts_at: string;
        organizer_slug: string;
    };
    ticket_type: { name: string; price_display: string };
};

type Props = { registrations: Row[] };

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

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

export default function MyRegistrations({ registrations }: Props) {
    return (
        <>
            <Head title="My registrations" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <Heading
                    title="My registrations"
                    description="Tickets you've reserved."
                />
                {registrations.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        You haven't registered for any events yet.
                        <div className="mt-2">
                            <Link
                                href="/events"
                                className="underline underline-offset-4"
                            >
                                Browse events
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3">Event</th>
                                    <th className="px-4 py-3">Reference</th>
                                    <th className="px-4 py-3">Ticket</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                {registrations.map((r) => (
                                    <tr key={r.reference} className="border-t">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/me/registrations/${r.reference}`}
                                                className="font-medium hover:underline"
                                            >
                                                {r.event.title}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {formatDate(r.event.starts_at)}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs">
                                            {r.reference}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {r.ticket_type.name} ·{' '}
                                            {r.ticket_type.price_display}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANTS[r.status] ??
                                                    'secondary'
                                                }
                                            >
                                                {r.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatDate(r.created_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

MyRegistrations.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);
