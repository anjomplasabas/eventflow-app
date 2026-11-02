import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';
import type { ReactNode } from 'react';

/**
 * Chrome for the authenticated workspace surfaces (dashboard, organizer,
 * admin, settings, catalogue when signed in). Persistent sidebar + compact
 * header strip. Content gets a sensible max-width + padding so pages that
 * previously relied on PublicShell's padded <main> wrapper still look
 * right inside the sidebar layout.
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
                <div className="mx-auto w-full max-w-6xl px-6 py-8">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
