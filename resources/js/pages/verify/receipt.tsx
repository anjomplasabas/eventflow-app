import { Badge } from '@/components/ui/badge';
import { Head } from '@inertiajs/react';

type VerifiedReceipt = {
    public_number: string;
    status: string;
    issued_at: string;
    event_title: string;
    organizer_name: string;
};

type Props = { receipt: VerifiedReceipt | null };

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString();
}

export default function VerifyReceipt({ receipt }: Props) {
    return (
        <>
            <Head
                title={
                    receipt !== null
                        ? `Verified · ${receipt.public_number}`
                        : 'Receipt not found'
                }
            />
            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 pt-12 lg:pt-16">
                {receipt === null ? (
                    <div className="rounded-2xl border border-dashed border-border/70 bg-card p-8 text-center">
                        <h1 className="mb-2 text-xl font-semibold">
                            Receipt not found
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            The receipt number you provided does not match any
                            issued receipt. If someone handed you this link,
                            please ask them to double-check it.
                        </p>
                    </div>
                ) : (
                    <div className="rounded-2xl border border-border/70 bg-card p-8">
                        <Badge
                            variant="secondary"
                            className="mb-3 bg-primary/10 text-primary"
                        >
                            Verified receipt
                        </Badge>
                        <h1 className="text-2xl font-semibold">
                            {receipt.event_title}
                        </h1>
                        <p className="text-muted-foreground">
                            Hosted by {receipt.organizer_name}
                        </p>
                        <dl className="mt-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-xs text-muted-foreground uppercase">
                                    Receipt number
                                </dt>
                                <dd className="mt-1 font-mono">
                                    {receipt.public_number}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground uppercase">
                                    Status
                                </dt>
                                <dd className="mt-1 capitalize">
                                    {receipt.status.replace('_', ' ')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground uppercase">
                                    Issued
                                </dt>
                                <dd className="mt-1">
                                    {formatDateTime(receipt.issued_at)}
                                </dd>
                            </div>
                        </dl>
                        <p className="mt-8 text-xs text-muted-foreground">
                            No personal information is shown on this public
                            page. Signed in attendees and organizers can view
                            full details in their account.
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}
