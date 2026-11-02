import OrganizerEventController from '@/actions/App/Http/Controllers/Organizer/EventController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Form, Head } from '@inertiajs/react';

type OrganizerSummary = { id: number; slug: string; name: string };
type VisibilityOption = { value: string; label: string };

type Props = {
    organizer: OrganizerSummary;
    visibilities: VisibilityOption[];
    timezones: string[];
};

export default function CreateEventPage({
    organizer,
    visibilities,
    timezones,
}: Props) {
    return (
        <>
            <Head title={`${organizer.name} · New event`} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
                <Heading
                    title="Create event"
                    description={`Add a new event for ${organizer.name}.`}
                />

                <Form
                    {...OrganizerEventController.store.form({
                        organizer: organizer.slug,
                    })}
                    encType="multipart/form-data"
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" name="title" required />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    required
                                    placeholder="lowercase-with-dashes"
                                />
                                <InputError message={errors.slug} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={4}
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cover_image">Cover image</Label>
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
                                        defaultValue="UTC"
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
                                        defaultValue="public"
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
                                        defaultValue={1}
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
                                <Input id="access_code" name="access_code" />
                                <InputError message={errors.access_code} />
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button type="submit" disabled={processing}>
                                    Create event
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateEventPage.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);
