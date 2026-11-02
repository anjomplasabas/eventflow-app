import { createInertiaApp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import { PublicShell } from '@/layouts/public-shell';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'EventFlow';

const PUBLIC_PAGE_PREFIXES = ['welcome', 'events/', 'me/', 'payments/'];

function isPublicShellPage(name: string): boolean {
    return PUBLIC_PAGE_PREFIXES.some(
        (prefix) => name === prefix || name.startsWith(prefix),
    );
}

function PublicLayout({ children }: { children: ReactNode }) {
    return <PublicShell>{children}</PublicShell>;
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case isPublicShellPage(name):
                return PublicLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#14b8a6',
    },
});

// This will set light / dark mode on load...
initializeTheme();
