import { createInertiaApp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { AdaptiveShell } from '@/layouts/adaptive-shell';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import { PublicShell } from '@/layouts/public-shell';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'EventFlow';

/**
 * Pages that stay public-feeling regardless of auth state. The landing
 * stays marketing-feel even if the visitor happens to be logged in; the
 * moment they click into the app the chrome adapts.
 */
const ALWAYS_PUBLIC_PAGES = new Set(['welcome']);

function AlwaysPublic({ children }: { children: ReactNode }) {
    return <PublicShell>{children}</PublicShell>;
}

function Adaptive({ children }: { children: ReactNode }) {
    return <AdaptiveShell>{children}</AdaptiveShell>;
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case ALWAYS_PUBLIC_PAGES.has(name):
                return AlwaysPublic;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return Adaptive;
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
