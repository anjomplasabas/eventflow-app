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
                <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-6">
                    <div className="space-y-2">
                        <Badge variant="outline">Private</Badge>
                        <h1 className="text-2xl font-semibold">
                            {event.title}
                        </h1>
                        <p className="text-muted-foreground">
                            Hosted by {organizer.name}
                        </p>
                    </div>
                    <form
                        method="get"
                        className="space-y-4 rounded-xl border p-6"
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
            <Head title={event.title} />
            <article className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                {event.cover_image_url && (
                    <img
                        src={event.cover_image_url}
                        alt=""
                        className="h-64 w-full rounded-xl object-cover"
                    />
                )}

                <header className="space-y-3">
                    <div className="flex items-center gap-2">
                        {detail?.category && (
                            <Badge variant="secondary">{detail.category}</Badge>
                        )}
                        {detail?.status && detail.status !== 'published' && (
                            <Badge variant="outline">{detail.status}</Badge>
                        )}
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {event.title}
                    </h1>
                    <p className="text-muted-foreground">
                        Hosted by {organizer.name}
                    </p>
                </header>

                <section className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="rounded-xl border p-4">
                        <div className="text-xs text-muted-foreground uppercase">
                            Starts
                        </div>
                        <div className="mt-1 text-sm">
                            {formatDateTime(event.starts_at)}
                        </div>
                    </div>
                    {detail?.ends_at && (
                        <div className="rounded-xl border p-4">
                            <div className="text-xs text-muted-foreground uppercase">
                                Ends
                            </div>
                            <div className="mt-1 text-sm">
                                {formatDateTime(detail.ends_at)}
                            </div>
                        </div>
                    )}
                    <div className="rounded-xl border p-4">
                        <div className="text-xs text-muted-foreground uppercase">
                            Timezone
                        </div>
                        <div className="mt-1 text-sm">{event.timezone}</div>
                    </div>
                </section>

                {detail?.description && (
                    <section className="prose dark:prose-invert max-w-none">
                        <p className="whitespace-pre-wrap">
                            {detail.description}
                        </p>
                    </section>
                )}

                {detail && (
                    <RegistrationSection
                        organizer={organizer}
                        event={detail}
                        ticketTypes={ticketTypes}
                        auth={auth}
                        accessCode={access_code ?? null}
                    />
                )}

                {detail?.attendee_instructions && (
                    <section className="rounded-xl border p-4">
                        <h2 className="mb-2 font-medium">Attendee notes</h2>
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {detail.attendee_instructions}
                        </p>
                    </section>
                )}

                {detail?.cancellation_policy && (
                    <section className="rounded-xl border p-4">
                        <h2 className="mb-2 font-medium">
                            Cancellation policy
                        </h2>
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {detail.cancellation_policy}
                        </p>
                    </section>
                )}
            </article>
        </>
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
            <section className="rounded-xl border p-6 text-sm text-muted-foreground">
                Registration is not currently open for this event.
            </section>
        );
    }

    if (ticketTypes.length === 0) {
        return (
            <section className="rounded-xl border p-6 text-sm text-muted-foreground">
                No ticket types are available yet. Check back soon.
            </section>
        );
    }

    if (!auth.user) {
        return (
            <section className="rounded-xl border p-6">
                <h2 className="mb-2 font-medium">Register for this event</h2>
                <p className="mb-4 text-sm text-muted-foreground">
                    Please sign in to continue.
                </p>
                <Button asChild>
                    <Link
                        href={`/login?intended=/events/${organizer.slug}/${event.slug}`}
                    >
                        Sign in to register
                    </Link>
                </Button>
            </section>
        );
    }

    const selected = ticketTypes.find((t) => t.id === selectedId) ?? null;
    const user = auth.user;

    return (
        <section className="rounded-xl border p-6">
            <h2 className="mb-4 font-medium">Register for this event</h2>
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
                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm ${selectedId === t.id ? 'border-foreground' : 'border-border'}`}
                                    >
                                        <input
                                            type="radio"
                                            name="ticket_type_id"
                                            value={t.id}
                                            checked={selectedId === t.id}
                                            onChange={() => setSelectedId(t.id)}
                                            className="mt-1"
                                        />
                                        <div className="flex-1">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-medium">
                                                    {t.name}
                                                </span>
                                                <span className="text-muted-foreground">
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

                        <div className="grid gap-4 md:grid-cols-2">
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
                            <InputError message={errors['attendee.phone']} />
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

                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="terms_accepted"
                                value="1"
                                required
                                className="mt-1"
                            />
                            <span>
                                I agree to the event terms and cancellation
                                policy.
                            </span>
                        </label>
                        <InputError message={errors.terms_accepted} />
                        <InputError message={errors.registration} />

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                Complete registration
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
