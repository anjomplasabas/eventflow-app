import ExportController from '@/actions/App/Http/Controllers/Organizer/ExportController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Form, Head } from '@inertiajs/react';

type Organizer = { id: number; slug: string; name: string };

type ExportRow = {
    uuid: string;
    type: string;
    status: string;
    row_count: number | null;
    filename: string | null;
    created_at: string | null;
    completed_at: string | null;
    expires_at: string;
    failure_reason: string | null;
    can_download: boolean;
};

type Props = { organizer: Organizer; exports: ExportRow[] };

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'secondary',
    processing: 'secondary',
    completed: 'default',
    failed: 'destructive',
    expired: 'outline',
};

const TYPE_LABEL: Record<string, string> = {
    receipt_register: 'Receipt register CSV',
    receipt_zip: 'Receipts ZIP',
};

export default function OrganizerExports({ organizer, exports }: Props) {
    return (
        <>
            <Head title={`${organizer.name} · Exports`} />
            <div className="flex flex-col gap-6">
                <Heading
                    title="Exports"
                    description="Generate downloadable receipt registers or ZIPs of all PDFs. Files are kept for 7 days then auto-deleted."
                />

                <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-border/70 bg-card p-5">
                        <h2 className="mb-1 text-base font-semibold">
                            Receipt register (CSV)
                        </h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Every receipt your organizer has issued, with totals
                            and status.
                        </p>
                        <Form
                            {...ExportController.storeRegister.form({
                                organizer: organizer.slug,
                            })}
                        >
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    Queue CSV export
                                </Button>
                            )}
                        </Form>
                    </div>
                    <div className="rounded-2xl border border-border/70 bg-card p-5">
                        <h2 className="mb-1 text-base font-semibold">
                            Batch PDF ZIP
                        </h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Bundle every receipt PDF into a single ZIP file.
                            Rendered on demand.
                        </p>
                        <Form
                            {...ExportController.storeZip.form({
                                organizer: organizer.slug,
                            })}
                        >
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    Queue ZIP export
                                </Button>
                            )}
                        </Form>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-border/70 bg-card">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left">
                            <tr>
                                <th className="px-4 py-3">Export</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Rows</th>
                                <th className="px-4 py-3">Requested</th>
                                <th className="px-4 py-3">Expires</th>
                                <th className="px-4 py-3 text-right">
                                    Download
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {exports.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        No exports yet. Queue one above.
                                    </td>
                                </tr>
                            ) : (
                                exports.map((row) => (
                                    <tr key={row.uuid} className="border-t">
                                        <td className="px-4 py-3">
                                            <div className="font-medium">
                                                {TYPE_LABEL[row.type] ??
                                                    row.type}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.filename ?? row.uuid}
                                            </div>
                                            {row.failure_reason && (
                                                <div className="mt-1 text-xs text-destructive">
                                                    {row.failure_reason}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANTS[
                                                        row.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {row.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.row_count ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatDateTime(row.created_at)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatDateTime(row.expires_at)}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {row.can_download ? (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <a
                                                        href={`/organizer/${organizer.slug}/exports/${row.uuid}/download`}
                                                    >
                                                        Download
                                                    </a>
                                                </Button>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}
