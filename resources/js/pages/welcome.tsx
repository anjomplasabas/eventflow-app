import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/react';

type Feature = {
    title: string;
    description: string;
};

const FEATURES: Feature[] = [
    {
        title: 'Capacity-safe registration',
        description:
            'Transactional seat reservation with row-level locking means no overselling, even under traffic spikes.',
    },
    {
        title: 'Smart waitlist',
        description:
            'FIFO waitlist with per-event opt-in. Auto-promotes attendees the moment a seat opens up.',
    },
    {
        title: 'E-receipts & tickets',
        description:
            'Opaque, verifiable tickets and PDF receipts generated through queues — delivered as fast as your attendees land.',
    },
    {
        title: 'Payments & refunds',
        description:
            'Pluggable gateways, signed webhook pipelines with idempotent dedupe, partial and full refund workflows.',
    },
    {
        title: 'Scheduled operations',
        description:
            'Abandoned carts expire themselves. Reminders go out before kickoff. Events close themselves.',
    },
    {
        title: 'Live check-in',
        description:
            'QR scanning and manual lookup with duplicate protection, built for crowded doors with slow networks.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="EventFlow — Capacity-safe event registration" />
            <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
                <BackgroundGlow />

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
                        {auth.user ? (
                            <Button asChild size="sm">
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <Button asChild size="sm" variant="outline">
                                <Link href={login()}>Sign in</Link>
                            </Button>
                        )}
                    </nav>
                </header>

                <main className="relative z-10 mx-auto flex w-full max-w-6xl flex-col gap-24 px-6 pb-24">
                    <section className="flex flex-col items-start gap-8 pt-16 lg:pt-24">
                        <span className="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-medium tracking-wide text-primary uppercase">
                            Production-grade event platform
                        </span>
                        <h1 className="max-w-3xl text-5xl leading-tight font-semibold tracking-tight sm:text-6xl">
                            Run{' '}
                            <span className="bg-gradient-to-br from-primary to-cyan-500 bg-clip-text text-transparent">
                                sold-out events
                            </span>{' '}
                            without the operational chaos.
                        </h1>
                        <p className="max-w-2xl text-lg text-muted-foreground">
                            EventFlow handles registration, waitlists, payments,
                            e-receipts, and door check-in with the correctness
                            guarantees you'd build if you had unlimited time.
                            Transactional, auditable, queued — and designed to
                            survive the moment tickets go live.
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button asChild size="lg">
                                <Link href="/events">Browse events</Link>
                            </Button>
                            {!auth.user && (
                                <Button asChild size="lg" variant="outline">
                                    <Link href={login()}>Sign in</Link>
                                </Button>
                            )}
                        </div>
                    </section>

                    <section className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {FEATURES.map((feature) => (
                            <article
                                key={feature.title}
                                className="group relative overflow-hidden rounded-2xl border border-border/70 bg-card p-6 transition hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5"
                            >
                                <div className="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <AppLogoIcon className="size-6" />
                                </div>
                                <h3 className="mb-2 text-lg font-semibold">
                                    {feature.title}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {feature.description}
                                </p>
                            </article>
                        ))}
                    </section>

                    <section className="rounded-3xl border border-border/70 bg-gradient-to-br from-primary/10 via-background to-background p-10 sm:p-14">
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="max-w-xl space-y-2">
                                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                    Ready to see it running?
                                </h2>
                                <p className="text-muted-foreground">
                                    Browse the public catalogue or sign in to
                                    manage your organizer workspace.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <Button asChild size="lg">
                                    <Link href="/events">
                                        See upcoming events
                                    </Link>
                                </Button>
                                {auth.user ? (
                                    <Button asChild size="lg" variant="outline">
                                        <Link href={dashboard()}>
                                            Open dashboard
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button asChild size="lg" variant="outline">
                                        <Link href={login()}>Sign in</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between border-t border-border/60 px-6 py-6 text-sm text-muted-foreground">
                    <span>
                        © {new Date().getFullYear()} EventFlow · Capacity-safe
                        event software.
                    </span>
                    <span className="hidden sm:inline">
                        Built with Laravel, Inertia & React.
                    </span>
                </footer>
            </div>
        </>
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
