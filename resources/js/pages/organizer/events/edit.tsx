import OrganizerEventController from '@/actions/App/Http/Controllers/Organizer/EventController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Form, Head } from '@inertiajs/react';

type OrganizerSummary = { id: number; slug: string; name: string };
type VisibilityOption = { value: string; label: string };

type EventFormPayload = {
    public_id: string;
    slug: string;
    title: string;
    description: string | null;
    category: string | null;
    visibility: string;
    access_code: string | null;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    timezone: string;
    capacity: number | null;
    per_attendee_limit: number;
    cover_image_url: string | null;
    cancellation_policy: string | null;
    attendee_instructions: string | null;
};

type Props = {
    organizer: OrganizerSummary;
    event: EventFormPayload;
    visibilities: VisibilityOption[];
    timezones: string[];
};

function toLocalInput(value: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function EditEventPage({
    organizer,
    event,
    visibilities,
    timezones,
}: Props) {
    return (
        <>
            <Head title={`${event.title} · Edit`} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={event.title}
                        description={`Editing event for ${organizer.name}.`}
                    />
                    <Badge variant="secondary">{event.status}</Badge>
                </div>

                {event.cover_image_url && (
                    <img
                        src={event.cover_image_url}
                        alt=""
                        className="h-40 w-full rounded-lg object-cover"
                    />
                )}

                <Form
                    {...OrganizerEventController.update.form({
                        organizer: organizer.slug,
                        event: event.public_id,
                    })}
                    encType="multipart/form-data"
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={event.title}
                                    required
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    defaultValue={event.slug}
                                    required
                                />
                                <InputError message={errors.slug} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={4}
                                    defaultValue={event.description ?? ''}
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cover_image">
                                    Replace cover image
                                </Label>
                                <Input
                                    id="cover_image"
                                    name="cover_image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                />
                                <InputError message={errors.cover_image} />
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="starts_at">Starts at</Label>
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="datetime-local"
                                        defaultValue={toLocalInput(
                                            event.starts_at,
                                        )}
                                        required
                                    />
                                    <InputError message={errors.starts_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ends_at">Ends at</Label>
                                    <Input
                                        id="ends_at"
                                        name="ends_at"
                                        type="datetime-local"
                                        defaultValue={toLocalInput(
                                            event.ends_at,
                                        )}
                                        required
                                    />
                                    <InputError message={errors.ends_at} />
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="timezone">Timezone</Label>
                                    <select
                                        id="timezone"
                                        name="timezone"
                                        defaultValue={event.timezone}
                                        required
                                        className="rounded-md border bg-background px-3 py-2 text-sm"
                                    >
                                        {timezones.map((tz) => (
                                            <option key={tz} value={tz}>
                                                {tz}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.timezone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="visibility">
                                        Visibility
                                    </Label>
                                    <select
                                        id="visibility"
                                        name="visibility"
                                        defaultValue={event.visibility}
                                        required
                                        className="rounded-md border bg-background px-3 py-2 text-sm"
                                    >
                                        {visibilities.map((v) => (
                                            <option
                                                key={v.value}
                                                value={v.value}
                                            >
                                                {v.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.visibility} />
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="capacity">Capacity</Label>
                                    <Input
                                        id="capacity"
                                        name="capacity"
                                        type="number"
                                        min={1}
                                        defaultValue={event.capacity ?? ''}
                                    />
                                    <InputError message={errors.capacity} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="per_attendee_limit">
                                        Tickets per attendee
                                    </Label>
                                    <Input
                                        id="per_attendee_limit"
                                        name="per_attendee_limit"
                                        type="number"
                                        min={1}
                                        max={50}
                                        defaultValue={event.per_attendee_limit}
                                        required
                                    />
                                    <InputError
                                        message={errors.per_attendee_limit}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="access_code">
                                    Access code (required when private)
                                </Label>
                                <Input
                                    id="access_code"
                                    name="access_code"
                                    defaultValue={event.access_code ?? ''}
                                />
                                <InputError message={errors.access_code} />
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

EditEventPage.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
