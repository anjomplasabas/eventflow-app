import AdminUserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Form, Head } from '@inertiajs/react';

type Option = { value: string; label: string };
type Organizer = { id: number; slug: string; name: string };
type Membership = { slug: string; name: string; role: string | null };
type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    memberships: Membership[];
};

type Props = {
    users: AdminUser[];
    organizers: Organizer[];
    roles: Option[];
    member_roles: Option[];
};

export default function AdminUsersIndex({
    users,
    organizers,
    roles,
    member_roles,
}: Props) {
    return (
        <>
            <Head title="Admin · Users" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <Heading
                    title="Users"
                    description="Manage roles, status, and organizer memberships."
                />

                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3">User</th>
                                <th className="px-4 py-3">Role</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Memberships</th>
                                <th className="px-4 py-3">Attach organizer</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-t align-top"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {user.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {user.email}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Form
                                            {...AdminUserController.updateRole.form(
                                                { user: user.id },
                                            )}
                                            className="flex items-center gap-2"
                                        >
                                            <select
                                                name="role"
                                                defaultValue={user.role}
                                                className="rounded-md border bg-background px-2 py-1 text-xs"
                                            >
                                                {roles.map((r) => (
                                                    <option
                                                        key={r.value}
                                                        value={r.value}
                                                    >
                                                        {r.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button type="submit" size="sm">
                                                Save
                                            </Button>
                                        </Form>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Form
                                            {...AdminUserController.updateStatus.form(
                                                { user: user.id },
                                            )}
                                            className="flex items-center gap-2"
                                        >
                                            <select
                                                name="status"
                                                defaultValue={user.status}
                                                className="rounded-md border bg-background px-2 py-1 text-xs"
                                            >
                                                {[
                                                    'active',
                                                    'suspended',
                                                    'disabled',
                                                ].map((s) => (
                                                    <option key={s} value={s}>
                                                        {s}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button type="submit" size="sm">
                                                Save
                                            </Button>
                                        </Form>
                                    </td>
                                    <td className="px-4 py-3">
                                        <ul className="space-y-1">
                                            {user.memberships.length === 0 && (
                                                <li className="text-xs text-muted-foreground">
                                                    None
                                                </li>
                                            )}
                                            {user.memberships.map((m) => (
                                                <li
                                                    key={m.slug}
                                                    className="flex items-center gap-2 text-xs"
                                                >
                                                    <Badge variant="outline">
                                                        {m.role}
                                                    </Badge>
                                                    <span>{m.name}</span>
                                                    <Form
                                                        {...AdminUserController.detachOrganizer.form(
                                                            {
                                                                user: user.id,
                                                                organizer:
                                                                    m.slug,
                                                            },
                                                        )}
                                                    >
                                                        <button
                                                            type="submit"
                                                            className="text-destructive underline"
                                                        >
                                                            remove
                                                        </button>
                                                    </Form>
                                                </li>
                                            ))}
                                        </ul>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Form
                                            {...AdminUserController.attachOrganizer.form(
                                                { user: user.id },
                                            )}
                                            className="flex flex-wrap items-center gap-2"
                                        >
                                            <select
                                                name="organizer_slug"
                                                required
                                                className="rounded-md border bg-background px-2 py-1 text-xs"
                                                defaultValue=""
                                            >
                                                <option value="" disabled>
                                                    Pick organizer…
                                                </option>
                                                {organizers.map((o) => (
                                                    <option
                                                        key={o.slug}
                                                        value={o.slug}
                                                    >
                                                        {o.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <select
                                                name="role"
                                                defaultValue="owner"
                                                className="rounded-md border bg-background px-2 py-1 text-xs"
                                            >
                                                {member_roles.map((r) => (
                                                    <option
                                                        key={r.value}
                                                        value={r.value}
                                                    >
                                                        {r.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button type="submit" size="sm">
                                                Attach
                                            </Button>
                                        </Form>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

AdminUsersIndex.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);
