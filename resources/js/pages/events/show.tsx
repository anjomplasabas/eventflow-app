import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Organizer = {
    slug: string;
    name: string;
    contact_email: string;
};

type AuthUser = {
    id: number;
    name: string;
    email: string;
};

type Auth = { user: AuthUser | null };

type EventTeaser = {
    public_id: string;
    title: string;
    slug: string;
    cover_image_url: string | null;
    starts_at: string;
    timezone: string;
};

type TicketTypeOption = {
    id: number;
    name: string;
    description: string | null;
    kind: string;
    price_display: string;
    min_per_order: number;
    max_per_order: number;
};

type EventDetail = EventTeaser & {
    description: string | null;
    category: string | null;
    status: string;
    ends_at: string;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    capacity: number | null;
    attendee_instructions: string | null;
    cancellation_policy: string | null;
    ticket_types: TicketTypeOption[];
};

type Props = {
    organizer: Organizer;
    event: EventDetail | EventTeaser;
    requires_access_code: boolean;
    code_invalid: boolean;
    auth: Auth;
    access_code?: string | null;
};

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function isDetail(e: EventDetail | EventTeaser): e is EventDetail {
    return 'status' in e;
}

export default function PublicEventShow({
    organizer,
    event,
    requires_access_code,
    code_invalid,
    auth,
    access_code,
}: Props) {
    const [code, setCode] = useState('');

    if (requires_access_code) {
        return (
            <>
                <Head title={`${event.title} · Private event`} />
                <div className="mx-auto w-full max-w-xl pt-12 lg:pt-16">
                    <div className="mb-6 space-y-3">
                        <Badge
                            variant="outline"
                            className="border-primary/40 bg-primary/10 text-primary"
                        >
                            Private event
                        </Badge>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {event.title}
                        </h1>
                        <p className="text-muted-foreground">
                            Hosted by {organizer.name}
                        </p>
                    </div>
                    <form
                        method="get"
                        className="space-y-4 rounded-2xl border border-border/70 bg-card p-6 shadow-sm"
                    >
                        <div className="space-y-2">
                            <Label htmlFor="code">Access code</Label>
                            <Input
                                id="code"
                                name="code"
                                value={code}
                                onChange={(e) => setCode(e.target.value)}
                                autoFocus
                                required
                            />
                            {code_invalid && (
                                <p className="text-sm text-destructive">
                                    That code is not valid.
                                </p>
                            )}
                        </div>
                        <Button type="submit" className="w-full">
                            Unlock event
                        </Button>
                    </form>
                </div>
            </>
        );
    }

    const detail = isDetail(event) ? event : null;
    const ticketTypes = detail?.ticket_types ?? [];

    return (
        <>
            <Head title={`${event.title} · EventFlow`} />
            <article className="flex flex-col gap-10 pt-10 lg:pt-14">
                <header className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            {detail?.category && (
                                <Badge
                                    variant="secondary"
                                    className="bg-primary/10 text-primary"
                                >
                                    {detail.category}
                                </Badge>
                            )}
                            {detail?.status &&
                                detail.status !== 'published' && (
                                    <Badge variant="outline">
                                        {detail.status.replace('_', ' ')}
                                    </Badge>
                                )}
                        </div>
                        <h1 className="max-w-3xl text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
                            {event.title}
                        </h1>
                        <p className="text-muted-foreground">
                            Hosted by{' '}
                            <span className="font-medium text-foreground">
                                {organizer.name}
                            </span>
                        </p>
                    </div>
                </header>

                <div className="relative aspect-[16/7] w-full overflow-hidden rounded-3xl border border-border/70">
                    {event.cover_image_url ? (
                        <img
                            src={event.cover_image_url}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="h-full w-full bg-gradient-to-br from-primary/25 via-cyan-400/15 to-sky-400/20">
                            <div className="h-full w-full bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.35),transparent_55%)]" />
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_380px]">
                    <div className="space-y-8">
                        <section className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <InfoTile
                                label="Starts"
                                value={formatDateTime(event.starts_at)}
                            />
                            {detail?.ends_at && (
                                <InfoTile
                                    label="Ends"
                                    value={formatDateTime(detail.ends_at)}
                                />
                            )}
                            <InfoTile label="Timezone" value={event.timezone} />
                        </section>

                        {detail?.description && (
                            <section className="space-y-3">
                                <h2 className="text-xl font-semibold tracking-tight">
                                    About this event
                                </h2>
                                <p className="leading-relaxed whitespace-pre-wrap text-muted-foreground">
                                    {detail.description}
                                </p>
                            </section>
                        )}

                        {detail?.attendee_instructions && (
                            <InfoCard title="Attendee notes">
                                {detail.attendee_instructions}
                            </InfoCard>
                        )}

                        {detail?.cancellation_policy && (
                            <InfoCard title="Cancellation policy">
                                {detail.cancellation_policy}
                            </InfoCard>
                        )}
                    </div>

                    {detail && (
                        <aside className="lg:sticky lg:top-6 lg:self-start">
                            <RegistrationSection
                                organizer={organizer}
                                event={detail}
                                ticketTypes={ticketTypes}
                                auth={auth}
                                accessCode={access_code ?? null}
                            />
                        </aside>
                    )}
                </div>
            </article>
        </>
    );
}

function InfoTile({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border border-border/70 bg-card p-4">
            <div className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-1 text-sm font-medium">{value}</div>
        </div>
    );
}

function InfoCard({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-2xl border border-border/70 bg-card p-5">
            <h2 className="mb-2 font-medium">{title}</h2>
            <p className="text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground">
                {children}
            </p>
        </section>
    );
}

function RegistrationSection({
    organizer,
    event,
    ticketTypes,
    auth,
    accessCode,
}: {
    organizer: Organizer;
    event: EventDetail;
    ticketTypes: TicketTypeOption[];
    auth: Auth;
    accessCode: string | null;
}) {
    const [selectedId, setSelectedId] = useState<number | null>(
        ticketTypes[0]?.id ?? null,
    );
    const idempotencyKey = useMemo(
        () => `reg-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
        [],
    );

    if (event.status !== 'published' && event.status !== 'scheduled') {
        return (
            <div className="rounded-2xl border border-border/70 bg-card p-6 text-sm text-muted-foreground">
                Registration is not currently open for this event.
            </div>
        );
    }

    if (ticketTypes.length === 0) {
        return (
            <div className="rounded-2xl border border-border/70 bg-card p-6 text-sm text-muted-foreground">
                No ticket types are available yet. Check back soon.
            </div>
        );
    }

    if (!auth.user) {
        return (
            <div className="rounded-2xl border border-border/70 bg-card p-6">
                <h2 className="mb-2 font-semibold">Register for this event</h2>
                <p className="mb-4 text-sm text-muted-foreground">
                    Sign in to secure your spot — takes a moment.
                </p>
                <Button asChild className="w-full">
                    <Link
                        href={`/login?intended=/events/${organizer.slug}/${event.slug}`}
                    >
                        Sign in to register
                    </Link>
                </Button>
            </div>
        );
    }

    const selected = ticketTypes.find((t) => t.id === selectedId) ?? null;
    const user = auth.user;

    return (
        <div className="rounded-2xl border border-border/70 bg-card p-6 shadow-sm">
            <h2 className="mb-1 text-lg font-semibold">Reserve your spot</h2>
            <p className="mb-5 text-sm text-muted-foreground">
                Capacity is handled in real time — your seat is locked the
                moment you submit.
            </p>
            <Form
                {...RegistrationController.store.form({
                    organizer: organizer.slug,
                    event: event.slug,
                })}
                className="space-y-4"
            >
                {({ errors, processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="idempotency_key"
                            value={idempotencyKey}
                        />
                        {accessCode && (
                            <input
                                type="hidden"
                                name="access_code"
                                value={accessCode}
                            />
                        )}

                        <div className="grid gap-2">
                            <Label>Ticket type</Label>
                            <div className="space-y-2">
                                {ticketTypes.map((t) => (
                                    <label
                                        key={t.id}
                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm transition ${
                                            selectedId === t.id
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:border-primary/40'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="ticket_type_id"
                                            value={t.id}
                                            checked={selectedId === t.id}
                                            onChange={() => setSelectedId(t.id)}
                                            className="mt-1 accent-[var(--primary)]"
                                        />
                                        <div className="flex-1">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-medium">
                                                    {t.name}
                                                </span>
                                                <span className="font-medium text-primary">
                                                    {t.price_display}
                                                </span>
                                            </div>
                                            {t.description && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {t.description}
                                                </p>
                                            )}
                                        </div>
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.ticket_type_id} />
                        </div>

                        <div className="grid gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="attendee_name">Full name</Label>
                                <Input
                                    id="attendee_name"
                                    name="attendee[name]"
                                    defaultValue={user.name}
                                    required
                                />
                                <InputError message={errors['attendee.name']} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="attendee_email">Email</Label>
                                <Input
                                    id="attendee_email"
                                    name="attendee[email]"
                                    type="email"
                                    defaultValue={user.email}
                                    required
                                />
                                <InputError
                                    message={errors['attendee.email']}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="attendee_phone">
                                    Phone (optional)
                                </Label>
                                <Input
                                    id="attendee_phone"
                                    name="attendee[phone]"
                                    type="tel"
                                />
                                <InputError
                                    message={errors['attendee.phone']}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="quantity">Quantity</Label>
                                <Input
                                    id="quantity"
                                    name="quantity"
                                    type="number"
                                    min={selected?.min_per_order ?? 1}
                                    max={selected?.max_per_order ?? 1}
                                    defaultValue={selected?.min_per_order ?? 1}
                                    required
                                />
                                <InputError message={errors.quantity} />
                            </div>
                        </div>

                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="terms_accepted"
                                value="1"
                                required
                                className="mt-1 accent-[var(--primary)]"
                            />
                            <span className="text-muted-foreground">
                                I agree to the event terms and cancellation
                                policy.
                            </span>
                        </label>
                        <InputError message={errors.terms_accepted} />
                        <InputError message={errors.registration} />

                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full"
                        >
                            Complete registration
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}
