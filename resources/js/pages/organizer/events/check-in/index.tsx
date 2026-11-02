import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/react';
import { Html5Qrcode } from 'html5-qrcode';
import { useEffect, useRef, useState } from 'react';

type Organizer = { id: number; slug: string; name: string };
type EventSummary = {
    public_id: string;
    title: string;
    starts_at: string;
    capacity: number | null;
};
type Stats = {
    confirmed_quantity: number;
    tickets_total: number;
    checked_in: number;
    capacity: number | null;
};
type CheckInRow = {
    id: number;
    ticket_token: string | null;
    attendee_name: string | null;
    attendee_email: string | null;
    registration_reference: string | null;
    method: string;
    scanned_at: string;
    staff_name: string | null;
    reversed: boolean;
};
type SearchResult = {
    public_token: string;
    attendee_name: string | null;
    attendee_email: string | null;
    registration_reference: string;
    checked_in: boolean;
    status: string;
};

type Props = {
    organizer: Organizer;
    event: EventSummary;
    stats: Stats;
    recent: CheckInRow[];
};

type Outcome =
    | { kind: 'idle' }
    | { kind: 'success'; attendee: string; reference: string }
    | { kind: 'already_checked_in'; token: string }
    | { kind: 'wrong_event' }
    | { kind: 'ticket_not_found' }
    | { kind: 'ticket_revoked' }
    | { kind: 'registration_not_confirmed'; status: string | null }
    | { kind: 'error'; message: string };

const OUTCOME_LABEL: Record<string, string> = {
    success: 'Admitted',
    already_checked_in: 'Already checked in',
    wrong_event: 'Wrong event',
    ticket_not_found: 'Ticket not found',
    ticket_revoked: 'Ticket revoked',
    registration_not_confirmed: 'Registration not confirmed',
    error: 'Error',
};

const OUTCOME_TONE: Record<
    string,
    { bg: string; border: string; text: string }
> = {
    success: {
        bg: 'bg-emerald-500/15',
        border: 'border-emerald-500/50',
        text: 'text-emerald-700',
    },
    already_checked_in: {
        bg: 'bg-amber-500/15',
        border: 'border-amber-500/50',
        text: 'text-amber-700',
    },
    wrong_event: {
        bg: 'bg-destructive/15',
        border: 'border-destructive/50',
        text: 'text-destructive',
    },
    ticket_not_found: {
        bg: 'bg-destructive/15',
        border: 'border-destructive/50',
        text: 'text-destructive',
    },
    ticket_revoked: {
        bg: 'bg-destructive/15',
        border: 'border-destructive/50',
        text: 'text-destructive',
    },
    registration_not_confirmed: {
        bg: 'bg-destructive/15',
        border: 'border-destructive/50',
        text: 'text-destructive',
    },
    error: {
        bg: 'bg-destructive/15',
        border: 'border-destructive/50',
        text: 'text-destructive',
    },
};

export default function CheckInPage({
    organizer,
    event,
    stats: initialStats,
    recent: initialRecent,
}: Props) {
    const [outcome, setOutcome] = useState<Outcome>({ kind: 'idle' });
    const [stats, setStats] = useState<Stats>(initialStats);
    const [recent, setRecent] = useState<CheckInRow[]>(initialRecent);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [cameraEnabled, setCameraEnabled] = useState(false);
    const scannerRef = useRef<Html5Qrcode | null>(null);
    const divId = 'eventflow-qr-scanner';
    const inFlight = useRef(false);

    const scanEndpoint = `/organizer/${organizer.slug}/events/${event.public_id}/check-in/scan`;
    const searchEndpoint = `/organizer/${organizer.slug}/events/${event.public_id}/check-in/search`;

    const submitToken = async (
        token: string,
        method: 'qr' | 'manual' = 'qr',
    ) => {
        if (inFlight.current) return;
        inFlight.current = true;
        try {
            const response = await fetch(scanEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ token, method }),
            });
            const body = await response.json();
            if (response.ok && body.outcome === 'success') {
                setOutcome({
                    kind: 'success',
                    attendee: body.check_in?.attendee_name ?? 'Attendee',
                    reference: body.check_in?.registration_reference ?? '',
                });
                setStats(body.stats);
                router.reload({ only: ['recent'], onSuccess: () => undefined });
                setRecent((prev) => [body.check_in, ...prev].slice(0, 20));
            } else {
                setOutcome(mapOutcome(body));
                if (body.stats) setStats(body.stats);
            }
        } catch (e) {
            setOutcome({
                kind: 'error',
                message: e instanceof Error ? e.message : 'Network error',
            });
        } finally {
            inFlight.current = false;
        }
    };

    const startCamera = async () => {
        if (scannerRef.current) return;
        try {
            const scanner = new Html5Qrcode(divId);
            scannerRef.current = scanner;
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: 260 },
                (decoded) => {
                    const token = extractToken(decoded);
                    if (token) void submitToken(token, 'qr');
                },
                () => undefined,
            );
            setCameraEnabled(true);
        } catch (e) {
            setOutcome({
                kind: 'error',
                message:
                    e instanceof Error ? e.message : 'Could not access camera',
            });
            scannerRef.current = null;
        }
    };

    const stopCamera = async () => {
        if (!scannerRef.current) return;
        try {
            await scannerRef.current.stop();
            scannerRef.current.clear();
        } catch {
            // ignore
        } finally {
            scannerRef.current = null;
            setCameraEnabled(false);
        }
    };

    useEffect(() => {
        return () => {
            if (scannerRef.current) {
                void scannerRef.current
                    .stop()
                    .catch(() => undefined)
                    .then(() => scannerRef.current?.clear())
                    .catch(() => undefined);
                scannerRef.current = null;
            }
        };
    }, []);

    const runSearch = async (q: string) => {
        setQuery(q);
        if (q.trim().length < 2) {
            setResults([]);
            return;
        }
        try {
            const response = await fetch(
                `${searchEndpoint}?q=${encodeURIComponent(q)}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            const body = await response.json();
            setResults(body.results ?? []);
        } catch {
            setResults([]);
        }
    };

    const outcomeTone =
        outcome.kind !== 'idle'
            ? (OUTCOME_TONE[outcome.kind] ?? OUTCOME_TONE.error)
            : null;

    return (
        <>
            <Head title={`${event.title} · Check-in`} />
            <div className="flex flex-col gap-6">
                <Heading
                    title="Check-in"
                    description={`${event.title} · ${new Date(event.starts_at).toLocaleString()}`}
                />

                <section className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <StatTile
                        label="Checked in"
                        value={stats.checked_in}
                        highlight
                    />
                    <StatTile
                        label="Tickets issued"
                        value={stats.tickets_total}
                    />
                    <StatTile
                        label="Confirmed seats"
                        value={stats.confirmed_quantity}
                    />
                    <StatTile label="Capacity" value={stats.capacity ?? '—'} />
                </section>

                {outcome.kind !== 'idle' && outcomeTone && (
                    <section
                        className={`rounded-2xl border p-4 ${outcomeTone.bg} ${outcomeTone.border}`}
                    >
                        <div
                            className={`text-sm font-semibold ${outcomeTone.text}`}
                        >
                            {OUTCOME_LABEL[outcome.kind] ?? 'Outcome'}
                        </div>
                        <div className="mt-1 text-sm">
                            {describeOutcome(outcome)}
                        </div>
                    </section>
                )}

                <section className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-2xl border border-border/70 bg-card p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-base font-semibold">
                                QR scanner
                            </h2>
                            {cameraEnabled ? (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => void stopCamera()}
                                >
                                    Stop camera
                                </Button>
                            ) : (
                                <Button
                                    size="sm"
                                    onClick={() => void startCamera()}
                                >
                                    Start camera
                                </Button>
                            )}
                        </div>
                        <div
                            id={divId}
                            className="aspect-square w-full overflow-hidden rounded-xl border border-dashed bg-muted/30"
                        />
                        <p className="mt-2 text-xs text-muted-foreground">
                            Point the camera at a ticket's QR code. Scans are
                            serialized so you can't double-admit by scanning
                            twice fast.
                        </p>
                    </div>

                    <div className="rounded-2xl border border-border/70 bg-card p-5">
                        <h2 className="mb-3 text-base font-semibold">
                            Manual lookup
                        </h2>
                        <Input
                            type="search"
                            placeholder="Search by name, email, or reference"
                            value={query}
                            onChange={(e) => void runSearch(e.target.value)}
                        />
                        <ul className="mt-3 flex max-h-72 flex-col gap-2 overflow-y-auto">
                            {results.map((row) => (
                                <li
                                    key={row.public_token}
                                    className="flex items-center justify-between gap-3 rounded-lg border border-border bg-background p-3 text-sm"
                                >
                                    <div>
                                        <div className="font-medium">
                                            {row.attendee_name ?? '—'}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.attendee_email ?? ''} ·{' '}
                                            {row.registration_reference}
                                        </div>
                                    </div>
                                    {row.checked_in ? (
                                        <Badge variant="outline">In</Badge>
                                    ) : (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                void submitToken(
                                                    row.public_token,
                                                    'manual',
                                                )
                                            }
                                        >
                                            Admit
                                        </Button>
                                    )}
                                </li>
                            ))}
                            {query.length >= 2 && results.length === 0 && (
                                <li className="text-sm text-muted-foreground">
                                    No matches.
                                </li>
                            )}
                        </ul>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-border/70 bg-card">
                    <h2 className="border-b px-5 py-3 text-sm font-semibold text-muted-foreground">
                        Recent check-ins
                    </h2>
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2">Attendee</th>
                                <th className="px-4 py-2">Reference</th>
                                <th className="px-4 py-2">Method</th>
                                <th className="px-4 py-2">At</th>
                                <th className="px-4 py-2">Staff</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recent.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No one checked in yet.
                                    </td>
                                </tr>
                            ) : (
                                recent.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="px-4 py-2">
                                            <div className="font-medium">
                                                {row.attendee_name ?? '—'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.attendee_email ?? ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-2 font-mono text-xs">
                                            {row.registration_reference}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground capitalize">
                                            {row.method}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {new Date(
                                                row.scanned_at,
                                            ).toLocaleTimeString()}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {row.staff_name ?? '—'}
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

function extractToken(decoded: string): string | null {
    // Accept either the raw token or a /verify/tickets/{token} URL.
    try {
        const url = new URL(decoded);
        const match = url.pathname.match(/\/verify\/tickets\/([a-z0-9]{32})/);
        return match ? match[1] : null;
    } catch {
        return /^[a-z0-9]{32}$/i.test(decoded) ? decoded.toLowerCase() : null;
    }
}

function describeOutcome(outcome: Outcome): string {
    switch (outcome.kind) {
        case 'success':
            return `${outcome.attendee} · ${outcome.reference}`;
        case 'already_checked_in':
            return 'This ticket has already been scanned.';
        case 'wrong_event':
            return "That ticket belongs to a different event — don't admit.";
        case 'ticket_not_found':
            return 'No ticket matches that code. Try the manual search.';
        case 'ticket_revoked':
            return 'This ticket has been revoked.';
        case 'registration_not_confirmed':
            return `Registration status is "${outcome.status ?? 'unknown'}", not confirmed.`;
        case 'error':
            return outcome.message;
        default:
            return '';
    }
}

function mapOutcome(body: {
    outcome?: string;
    details?: string | null;
}): Outcome {
    const kind = body.outcome ?? 'error';
    if (kind === 'registration_not_confirmed') {
        return { kind, status: body.details ?? null };
    }
    if (kind === 'already_checked_in') {
        return { kind, token: body.details ?? '' };
    }
    if (
        kind === 'wrong_event' ||
        kind === 'ticket_not_found' ||
        kind === 'ticket_revoked'
    ) {
        return { kind };
    }
    return { kind: 'error', message: body.details ?? 'Unknown error' };
}
