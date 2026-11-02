import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { Auth } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type ShellLink = {
    label: string;
    href: string;
};

type PublicShellProps = {
    children: ReactNode;
    showGlow?: boolean;
    width?: 'default' | 'wide';
};

/**
 * Shared chrome for every surface. Public visitors see Browse + Sign in; auth
 * users see the sections relevant to them (Dashboard, My registrations, their
 * organizer workspace, and admin console when applicable).
 */
export function PublicShell({
    children,
    showGlow = true,
    width = 'default',
}: PublicShellProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const user = auth?.user ?? null;
    const memberships = auth?.organizer_memberships ?? [];
    const isAdmin = auth?.is_system_admin ?? false;
    const primaryOrganizer = memberships[0];
    const { isCurrentUrl } = useCurrentUrl();

    const links: ShellLink[] = [{ label: 'Browse events', href: '/events' }];
    if (user) {
        links.push({ label: 'Dashboard', href: '/dashboard' });
        links.push({ label: 'My registrations', href: '/me/registrations' });
        if (primaryOrganizer) {
            links.push({
                label: primaryOrganizer.name,
                href: `/organizer/${primaryOrganizer.slug}/events`,
            });
        }
        if (isAdmin) {
            links.push({ label: 'Admin', href: '/admin/users' });
        }
    }

    const maxWidthClass = width === 'wide' ? 'max-w-7xl' : 'max-w-6xl';

    return (
        <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
            {showGlow && <BackgroundGlow />}

            <header
                className={`relative z-10 mx-auto flex w-full ${maxWidthClass} items-center justify-between gap-4 px-6 py-6`}
            >
                <Link
                    href="/"
                    className="flex items-center gap-2 font-semibold tracking-tight"
                >
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <AppLogoIcon className="size-6" />
                    </span>
                    <span className="text-lg">EventFlow</span>
                </Link>
                <nav className="hidden items-center gap-1 md:flex">
                    {links.map((link) => {
                        const active = isCurrentUrl(link.href);
                        return (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={`rounded-md px-3 py-1.5 text-sm transition ${
                                    active
                                        ? 'bg-primary/10 text-primary'
                                        : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground'
                                }`}
                            >
                                {link.label}
                            </Link>
                        );
                    })}
                </nav>
                <div className="flex items-center gap-2">
                    {user ? (
                        <UserMenu
                            name={user.name}
                            email={user.email}
                            links={links}
                        />
                    ) : (
                        <>
                            <Button asChild size="sm" variant="ghost">
                                <Link href="/login">Sign in</Link>
                            </Button>
                            <Button asChild size="sm">
                                <Link href="/events">Browse events</Link>
                            </Button>
                        </>
                    )}
                </div>
            </header>

            <main
                className={`relative z-10 mx-auto w-full ${maxWidthClass} px-6 pb-24`}
            >
                {children}
            </main>

            <footer
                className={`relative z-10 mx-auto flex w-full ${maxWidthClass} items-center justify-between border-t border-border/60 px-6 py-6 text-sm text-muted-foreground`}
            >
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

function UserMenu({
    name,
    email,
    links,
}: {
    name: string;
    email: string;
    links: ShellLink[];
}) {
    const initial = (name || email || '?').charAt(0).toUpperCase();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex h-9 w-9 items-center justify-center rounded-full bg-primary/15 text-sm font-medium text-primary transition hover:bg-primary/25"
                    aria-label="Account menu"
                >
                    {initial}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel>
                    <div className="font-medium">{name}</div>
                    <div className="text-xs text-muted-foreground">{email}</div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <div className="md:hidden">
                    {links.map((link) => (
                        <DropdownMenuItem key={link.href} asChild>
                            <Link href={link.href}>{link.label}</Link>
                        </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                </div>
                <DropdownMenuItem asChild>
                    <Link href="/settings/profile">Settings</Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="w-full text-left"
                    >
                        Sign out
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
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
