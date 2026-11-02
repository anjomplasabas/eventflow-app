import OrganizerEventController from '@/actions/App/Http/Controllers/Organizer/EventController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Form, Head, Link } from '@inertiajs/react';

type OrganizerSummary = {
    id: number;
    slug: string;
    name: string;
};

type EventListItem = {
    public_id: string;
    title: string;
    slug: string;
    status: string;
    visibility: string;
    starts_at: string | null;
    ends_at: string | null;
    capacity: number | null;
    cover_image_url: string | null;
    allowed_transitions: string[];
};

type Props = {
    organizer: OrganizerSummary;
    events: EventListItem[];
};

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    scheduled: 'default',
    published: 'default',
    sold_out: 'default',
    completed: 'outline',
    cancelled: 'destructive',
    archived: 'outline',
};

function formatDateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

export default function OrganizerEventsIndex({ organizer, events }: Props) {
    return (
        <>
            <Head title={`${organizer.name} · Events`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Events"
                        description={`Manage events for ${organizer.name}.`}
                    />
                    <Button asChild>
                        <Link
                            href={
                                OrganizerEventController.create({
                                    organizer: organizer.slug,
                                }).url
                            }
                        >
                            New event
                        </Link>
                    </Button>
                </div>

                {events.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        No events yet. Create your first one to get started.
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3">Title</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Visibility</th>
                                    <th className="px-4 py-3">Starts</th>
                                    <th className="px-4 py-3">Capacity</th>
                                    <th className="px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.map((event) => (
                                    <tr
                                        key={event.public_id}
                                        className="border-t"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                className="font-medium hover:underline"
                                                href={
                                                    OrganizerEventController.edit(
                                                        {
                                                            organizer:
                                                                organizer.slug,
                                                            event: event.public_id,
                                                        },
                                                    ).url
                                                }
                                            >
                                                {event.title}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {event.slug}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANTS[
                                                        event.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {event.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground capitalize">
                                            {event.visibility}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {formatDateTime(event.starts_at)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {event.capacity ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                {event.allowed_transitions.includes(
                                                    'published',
                                                ) && (
                                                    <Form
                                                        {...OrganizerEventController.publish.form(
                                                            {
                                                                organizer:
                                                                    organizer.slug,
                                                                event: event.public_id,
                                                            },
                                                        )}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Publish
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                                {event.allowed_transitions.includes(
                                                    'cancelled',
                                                ) && (
                                                    <Form
                                                        {...OrganizerEventController.cancel.form(
                                                            {
                                                                organizer:
                                                                    organizer.slug,
                                                                event: event.public_id,
                                                            },
                                                        )}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="destructive"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                Cancel
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                            </div>
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

OrganizerEventsIndex.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);
