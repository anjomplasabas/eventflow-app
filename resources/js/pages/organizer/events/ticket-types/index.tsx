import TicketTypeController from '@/actions/App/Http/Controllers/Organizer/TicketTypeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';

type Option = { value: string; label: string };
type Organizer = { id: number; slug: string; name: string };
type EventSummary = { public_id: string; title: string };

type TicketTypeItem = {
    id: number;
    name: string;
    description: string | null;
    kind: string;
    price_minor: number;
    currency: string;
    price_display: string;
    capacity: number | null;
    min_per_order: number;
    max_per_order: number;
    active: boolean;
    display_order: number;
};

type Props = {
    organizer: Organizer;
    event: EventSummary;
    ticket_types: TicketTypeItem[];
    kinds: Option[];
};

export default function TicketTypesIndex({
    organizer,
    event,
    ticket_types,
    kinds,
}: Props) {
    const [showForm, setShowForm] = useState(ticket_types.length === 0);

    return (
        <>
            <Head title={`${event.title} · Ticket types`} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Ticket types"
                        description={`Manage ticket types for ${event.title}.`}
                    />
                    {!showForm && (
                        <Button onClick={() => setShowForm(true)}>
                            Add ticket type
                        </Button>
                    )}
                </div>

                {showForm && (
                    <section className="rounded-xl border p-6">
                        <h2 className="mb-4 font-medium">New ticket type</h2>
                        <Form
                            {...TicketTypeController.store.form({
                                organizer: organizer.slug,
                                event: event.public_id,
                            })}
                            onSuccess={() => setShowForm(false)}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="kind">Kind</Label>
                                            <select
                                                id="kind"
                                                name="kind"
                                                defaultValue="free"
                                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                            >
                                                {kinds.map((k) => (
                                                    <option
                                                        key={k.value}
                                                        value={k.value}
                                                    >
                                                        {k.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.kind} />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="price_minor">
                                                Price (minor units)
                                            </Label>
                                            <Input
                                                id="price_minor"
                                                name="price_minor"
                                                type="number"
                                                min={0}
                                                defaultValue={0}
                                                required
                                            />
                                            <InputError
                                                message={errors.price_minor}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="currency">
                                                Currency
                                            </Label>
                                            <Input
                                                id="currency"
                                                name="currency"
                                                defaultValue="USD"
                                                maxLength={3}
                                                required
                                            />
                                            <InputError
                                                message={errors.currency}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="capacity">
                                                Capacity
                                            </Label>
                                            <Input
                                                id="capacity"
                                                name="capacity"
                                                type="number"
                                                min={1}
                                            />
                                            <InputError
                                                message={errors.capacity}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="min_per_order">
                                                Min per order
                                            </Label>
                                            <Input
                                                id="min_per_order"
                                                name="min_per_order"
                                                type="number"
                                                min={1}
                                                defaultValue={1}
                                                required
                                            />
                                            <InputError
                                                message={errors.min_per_order}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="max_per_order">
                                                Max per order
                                            </Label>
                                            <Input
                                                id="max_per_order"
                                                name="max_per_order"
                                                type="number"
                                                min={1}
                                                defaultValue={1}
                                                required
                                            />
                                            <InputError
                                                message={errors.max_per_order}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="display_order">
                                                Display order
                                            </Label>
                                            <Input
                                                id="display_order"
                                                name="display_order"
                                                type="number"
                                                min={0}
                                                defaultValue={0}
                                                required
                                            />
                                            <InputError
                                                message={errors.display_order}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <textarea
                                            id="description"
                                            name="description"
                                            rows={2}
                                            className="rounded-md border bg-background px-3 py-2 text-sm"
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>

                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="active"
                                            value="1"
                                            defaultChecked
                                        />
                                        Active
                                    </label>

                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setShowForm(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create ticket type
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                {ticket_types.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        No ticket types yet. Add one above to start accepting
                        registrations.
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3">Name</th>
                                    <th className="px-4 py-3">Kind</th>
                                    <th className="px-4 py-3">Price</th>
                                    <th className="px-4 py-3">Capacity</th>
                                    <th className="px-4 py-3">Per order</th>
                                    <th className="px-4 py-3">Active</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {ticket_types.map((t) => (
                                    <tr key={t.id} className="border-t">
                                        <td className="px-4 py-3 font-medium">
                                            {t.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground capitalize">
                                            {t.kind}
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.price_display}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {t.capacity ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {t.min_per_order}–{t.max_per_order}
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.active ? (
                                                <Badge>on</Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    off
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Form
                                                {...TicketTypeController.destroy.form(
                                                    {
                                                        organizer:
                                                            organizer.slug,
                                                        event: event.public_id,
                                                        ticket_type: t.id,
                                                    },
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </Form>
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

TicketTypesIndex.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);
