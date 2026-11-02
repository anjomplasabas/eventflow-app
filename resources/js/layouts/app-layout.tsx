import { WorkspaceShell } from '@/layouts/workspace-shell';
import type { BreadcrumbItem } from '@/types';

/**
 * Default layout for authenticated workspace pages (dashboard, organizer,
 * admin, settings). Public-facing attendee pages use PublicShell directly
 * via the layout picker in app.tsx.
 */
export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <WorkspaceShell breadcrumbs={breadcrumbs}>{children}</WorkspaceShell>
    );
}
