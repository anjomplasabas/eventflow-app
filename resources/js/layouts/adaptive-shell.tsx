import { PublicShell } from '@/layouts/public-shell';
import { WorkspaceShell } from '@/layouts/workspace-shell';
import type { Auth, BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Chrome that follows the user's session state: logged-in users get the
 * workspace sidebar on every page (so the mental model stays "I'm inside
 * EventFlow"); logged-out visitors get the marketing-feel PublicShell for
 * the same routes. The content inside is identical — only the chrome
 * changes to match the audience.
 */
export function AdaptiveShell({
    children,
    breadcrumbs = [],
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAuthenticated = Boolean(auth?.user);

    if (isAuthenticated) {
        return (
            <WorkspaceShell breadcrumbs={breadcrumbs}>
                {children}
            </WorkspaceShell>
        );
    }

    return <PublicShell>{children}</PublicShell>;
}
