import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';

type ReceiptItem = {
    label: string;
    description: string | null;
    quantity: number;
    unit_price_display: string;
    line_total_display: string;
};

type Snapshot = Record<string, string | null | undefined>;

type Receipt = {
    public_number: string;
    status: string;
    issued_at: string;
    subtotal_display: string;
    total_display: string;
    currency: string;
    organizer: Snapshot;
    event: Snapshot;
    attendee: Snapshot;
    items: ReceiptItem[];
    pdf_ready: boolean;
};

type Props = { receipt: Receipt };

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString();
}

export default function ReceiptShow({ receipt }: Props) {
    return (
        <>
            <Head title={`Receipt ${receipt.public_number}`} />
            <div className="flex flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Badge
                            variant="secondary"
                            className="mb-2 bg-primary/10 text-primary"
                        >
                            Receipt {receipt.public_number}
                        </Badge>
                        <Heading
                            title={String(receipt.event.title ?? 'Receipt')}
                            description={`Hosted by ${receipt.organizer.name ?? ''}`}
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button asChild>
                            <a
                                href={`/me/receipts/${receipt.public_number}/download`}
                            >
                                {receipt.pdf_ready
                                    ? 'Download PDF'
                                    : 'Generate PDF'}
                            </a>
                        </Button>
                    </div>
                </div>

                <section className="rounded-2xl border border-border/70 bg-card p-6">
                    <dl className="grid grid-cols-2 gap-4 text-sm">
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
                                Issued
                            </dt>
                            <dd className="mt-1">
                                {formatDateTime(receipt.issued_at)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Attendee
                            </dt>
                            <dd className="mt-1">
                                {receipt.attendee.name}
                                <div className="text-muted-foreground">
                                    {receipt.attendee.email}
                                </div>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Registration
                            </dt>
                            <dd className="mt-1 font-mono">
                                {receipt.attendee.registration_reference}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section className="overflow-hidden rounded-2xl border border-border/70 bg-card">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-4 py-3">Item</th>
                                <th className="px-4 py-3 text-right">Qty</th>
                                <th className="px-4 py-3 text-right">Unit</th>
                                <th className="px-4 py-3 text-right">
                                    Line total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipt.items.map((item) => (
                                <tr key={item.label} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {item.label}
                                        </div>
                                        {item.description && (
                                            <div className="text-xs text-muted-foreground">
                                                {item.description}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {item.quantity}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono">
                                        {item.unit_price_display}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono">
                                        {item.line_total_display}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t bg-muted/20">
                                <td
                                    colSpan={3}
                                    className="px-4 py-3 text-right text-xs text-muted-foreground uppercase"
                                >
                                    Subtotal
                                </td>
                                <td className="px-4 py-3 text-right font-mono">
                                    {receipt.subtotal_display}
                                </td>
                            </tr>
                            <tr className="border-t bg-muted/20">
                                <td
                                    colSpan={3}
                                    className="px-4 py-3 text-right text-sm font-semibold"
                                >
                                    Total
                                </td>
                                <td className="px-4 py-3 text-right font-mono text-lg font-semibold">
                                    {receipt.total_display}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <section className="rounded-2xl border border-border/70 bg-muted/20 p-4 text-sm text-muted-foreground">
                    Anyone can verify this receipt at{' '}
                    <Link
                        href={`/verify/receipts/${receipt.public_number}`}
                        className="font-mono text-primary hover:underline"
                    >
                        /verify/receipts/{receipt.public_number}
                    </Link>
                    . No personal information is shown on the public page.
                </section>
            </div>
        </>
    );
}
