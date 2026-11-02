import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type PublicShellProps = {
    children: ReactNode;
    showGlow?: boolean;
};

/**
 * Shared chrome for the public-facing marketing + catalogue pages so the
 * landing page, event listing, and event detail all feel like one product.
 */
export function PublicShell({ children, showGlow = true }: PublicShellProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const user = auth?.user ?? null;

    return (
        <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
            {showGlow && <BackgroundGlow />}

            <header className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                <Link
                    href="/"
                    className="flex items-center gap-2 font-semibold tracking-tight"
                >
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <AppLogoIcon className="size-6" />
                    </span>
                    <span className="text-lg">EventFlow</span>
                </Link>
                <nav className="flex items-center gap-3">
                    <Link
                        href="/events"
                        className="text-sm text-muted-foreground transition hover:text-foreground"
                    >
                        Browse events
                    </Link>
                    {user ? (
                        <>
                            <Link
                                href="/me/registrations"
                                className="hidden text-sm text-muted-foreground transition hover:text-foreground sm:inline"
                            >
                                My registrations
                            </Link>
                            <Button asChild size="sm">
                                <Link href="/dashboard">Dashboard</Link>
                            </Button>
                        </>
                    ) : (
                        <Button asChild size="sm" variant="outline">
                            <Link href="/login">Sign in</Link>
                        </Button>
                    )}
                </nav>
            </header>

            <main className="relative z-10 mx-auto w-full max-w-6xl px-6 pb-24">
                {children}
            </main>

            <footer className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between border-t border-border/60 px-6 py-6 text-sm text-muted-foreground">
                <span>
                    © {new Date().getFullYear()} EventFlow · Capacity-safe event
                    software.
                </span>
                <span className="hidden sm:inline">
                    Built with Laravel, Inertia & React.
                </span>
            </footer>
        </div>
    );
}

function BackgroundGlow() {
    return (
        <div
            aria-hidden
            className="pointer-events-none absolute inset-0 overflow-hidden"
        >
            <div className="absolute top-[-10%] left-1/2 h-[520px] w-[520px] -translate-x-1/2 rounded-full bg-primary/25 blur-[120px]" />
            <div className="absolute top-[30%] right-[-10%] h-[420px] w-[420px] rounded-full bg-cyan-400/20 blur-[120px]" />
            <div className="absolute bottom-[-20%] left-[-10%] h-[460px] w-[460px] rounded-full bg-sky-400/20 blur-[140px]" />
        </div>
    );
}
