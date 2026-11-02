import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';
import type { ReactNode } from 'react';

/**
 * Chrome for the authenticated workspace surfaces (dashboard, organizer,
 * admin, settings). Uses a persistent sidebar for multi-section navigation
 * and a compact header strip with the sidebar trigger + breadcrumbs. No
 * decorative glow — this is a tool, not a landing page.
 */
export function WorkspaceShell({
    children,
    breadcrumbs = [],
}: AppLayoutProps & { children: ReactNode }) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
