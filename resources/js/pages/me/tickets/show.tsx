import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Head } from '@inertiajs/react';

type Ticket = {
    public_token: string;
    status: string;
    issued_at: string;
    checked_in_at: string | null;
    attendee_name: string;
    attendee_email: string;
    ticket_type: string | null;
    event: { title: string; starts_at: string; timezone: string };
    qr_image_ready: boolean;
};

type Props = { ticket: Ticket };

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString();
}

export default function TicketShow({ ticket }: Props) {
    const checkedIn = ticket.checked_in_at !== null;
    const statusVariant = checkedIn ? 'default' : 'secondary';

    return (
        <>
            <Head title={`Ticket · ${ticket.event.title}`} />
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                <Heading
                    title={ticket.event.title}
                    description={`Starts ${formatDateTime(ticket.event.starts_at)}`}
                />

                <div className="overflow-hidden rounded-2xl border border-border/70 bg-card">
                    <div className="flex flex-col items-center gap-3 bg-gradient-to-br from-primary/10 via-background to-background p-6">
                        {ticket.qr_image_ready ? (
                            <img
                                src={`/me/tickets/${ticket.public_token}/qr`}
                                alt="Ticket QR code"
                                className="h-64 w-64 rounded-lg border bg-white p-3"
                            />
                        ) : (
                            <div className="flex h-64 w-64 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground">
                                QR code is being generated…
                            </div>
                        )}
                        <div className="text-center">
                            <div className="text-xs text-muted-foreground uppercase">
                                Ticket token
                            </div>
                            <div className="mt-1 font-mono text-xs tracking-wider text-muted-foreground">
                                {ticket.public_token}
                            </div>
                        </div>
                    </div>
                    <div className="grid gap-3 border-t p-6 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">
                                Attendee
                            </span>
                            <div className="text-right">
                                <div className="font-medium">
                                    {ticket.attendee_name}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {ticket.attendee_email}
                                </div>
                            </div>
                        </div>
                        {ticket.ticket_type && (
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Ticket type
                                </span>
                                <span className="font-medium">
                                    {ticket.ticket_type}
                                </span>
                            </div>
                        )}
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">
                                Status
                            </span>
                            <Badge variant={statusVariant}>
                                {checkedIn ? 'Checked in' : ticket.status}
                            </Badge>
                        </div>
                        {checkedIn && (
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Checked in at</span>
                                <span>
                                    {formatDateTime(ticket.checked_in_at!)}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                <p className="text-center text-xs text-muted-foreground">
                    Show the QR code at the door. One scan per ticket; staff
                    cannot admit a revoked or duplicate ticket.
                </p>
            </div>
        </>
    );
}
