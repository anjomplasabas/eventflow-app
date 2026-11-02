# EventFlow

Production-style event registration and check-in platform built with Laravel 13, Inertia.js, and React. Implementation is phased per `app-spec.md`.

## Status

- **Batch 1 — Foundation** ✅ Roles, organizers + team membership, event CRUD with policies, audit log, `Money` value object, deterministic seeders, admin UI.
- **Batch 2 — Registration Core** ✅ Ticket types, transactional capacity reservation with `lockForUpdate`, free-registration flow, idempotency keys, public ticket selection + confirmation + cancellation, concurrency test proving the last-seat invariant.
- **Batch 3 — Queues & Notifications** ✅ Queued mailables, scheduled commands for expiring abandoned registrations, event reminders, and auto-completing events.
- **Batch 4 — Waitlist** ✅ FIFO waitlist with per-event opt-in and optional capacity cap, auto-promotion on cancel/expire with lockForUpdate, free → confirmed and paid → payment_pending, dedicated join/promoted notifications.
- **Batch 5 — Payments & Webhooks** ✅ `PaymentGateway` interface with a sandbox adapter, signed-webhook inbox with idempotent `provider_event_id` dedupe, `ProcessPaymentWebhook` job on the `critical` queue, out-of-order replay protection, refund workflow with partial/full refund status transitions.
- **Batch 6 — Tickets & E-Receipts** ✅ Immutable receipt snapshots with opaque `RCPT-XXXXXXXXXX` numbers, dompdf-rendered PDFs stored to the private `local` disk via queued jobs, per-seat QR tickets with 32-char opaque tokens, authenticated downloads, public verification page with no PII, organizer-triggered CSV receipt register + batch ZIP exports with 7-day expiry and daily cleanup.

Remaining batches: check-in/reporting · hardening.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link        # required for cover image uploads to render in dev
npm install
npm run build
```

Then either `composer dev` or run `php artisan serve` + `npm run dev` separately.

## Seeded demo credentials

Password for every seeded account: `password`

| Role            | Email                | Organizer context     |
| --------------- | -------------------- | --------------------- |
| System admin    | admin@eventflow.test | —                     |
| Organizer owner | alice@pulse.test     | Pulse Collective      |
| Organizer staff | bob@pulse.test       | Pulse Collective      |
| Organizer owner | carla@northstar.test | Northstar Conferences |
| Attendee        | dana@attendee.test   | —                     |

Seeded organizers:

- **Pulse Collective** (`pulse-collective`) — music / culture promoter. Harbor Lights Festival, Midnight Sessions, Acoustic Garden, Riverside Fest (completed), a cancelled showcase, a draft solstice rave, and the private rooftop preview (access code `AFTERGLOW-27`).
- **Northstar Conferences** (`northstar-conferences`) — business / tech conferences. Northstar Summit 2027, AI Builders Day, Fintech Founders Breakfast, Startup Pitch Night, Northstar Summit 2026 (completed), a cancelled growth masterclass, and the Executive Leadership Retreat (access code `LEAD-NS27`).

Each published event ships with 1–4 realistic ticket types (free + paid tiers in USD minor units) and multi-paragraph descriptions with attendee notes and cancellation policies, so detail pages have real content to show.

## Local URLs

- `/` — welcome
- `/events` — public event listing
- `/events/{organizer}/{event}` — public detail (private events require `?code=`)
- `/organizer/{organizer}/events` — organizer event CRUD (auth required, owner only)
- `/admin/users` — admin role/membership management (system admin only)
- `/up` — health check

## Testing

```bash
composer ci:check    # format + types + phpstan + pest
composer test        # same, used in CI
vendor/bin/pest      # just the test suite
```

Current suite: **207 tests / 571 assertions** across unit, feature, policy, concurrency, scheduled-command, waitlist-promotion, and payment-webhook coverage.

## Architecture notes

- **Enums** (`app/Enums/`) carry state machines; `EventStatus::canTransitionTo()` is the source of truth for publish/cancel/archive transitions.
- **Policies** are attached to models via `#[UsePolicy]` attributes (Laravel 13).
- **Actions** (`app/Actions/Events/`) wrap mutations in a DB transaction and emit audit entries through `AuditLogger`.
- **Money** (`app/Support/Money.php`) is a readonly value object using integer minor units; floats are never used for currency.
- **Wayfinder** generates typed route helpers under `resources/js/actions/` and `resources/js/routes/`; frontend code should import from those rather than hard-coding URLs. Regenerate with `php artisan wayfinder:generate` after route changes.

## Queue layout

Notifications and jobs are dispatched to named queues so a Redis worker (or Horizon) can prioritise them. Set `QUEUE_CONNECTION=redis` in `.env` and run one worker per queue in production:

```bash
php artisan queue:work redis --queue=critical
php artisan queue:work redis --queue=registrations
php artisan queue:work redis --queue=notifications
php artisan queue:work redis --queue=documents
php artisan queue:work redis --queue=exports
php artisan queue:work redis --queue=default
```

| Queue           | Workload                                                    |
| --------------- | ----------------------------------------------------------- |
| `critical`      | Payment webhooks (Batch 5)                                  |
| `registrations` | Registration finalization, waitlist promotion (Batches 2/4) |
| `notifications` | Attendee emails (confirmation, cancellation, reminder)      |
| `documents`     | PDF receipts + QR tickets (dompdf, endroid/qr-code)         |
| `exports`       | Receipt-register CSV + batch PDF ZIP                        |
| `default`       | Everything else                                             |

Tests use `QUEUE_CONNECTION=sync` (see `phpunit.xml`). Notification dispatches happen via `DB::afterCommit`, so a failed registration transaction never leaks an email.

## Scheduled commands

Run `php artisan schedule:work` (or add `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` to cron) in production:

| Command                                  | Cadence      | Purpose                                                                       |
| ---------------------------------------- | ------------ | ----------------------------------------------------------------------------- |
| `eventflow:expire-pending-registrations` | Every 5 min  | Transitions `payment_pending` registrations older than 30 min to `expired`    |
| `eventflow:send-event-reminders`         | Every 15 min | Queues reminder emails for confirmed attendees whose event starts within 24 h |
| `eventflow:mark-events-completed`        | Hourly       | Transitions `Published`/`SoldOut` events past their `ends_at` to `Completed`  |
| `eventflow:delete-expired-exports`       | Daily        | Removes export files past their 7-day retention; keeps the audit row          |

Every scheduled command is idempotent; re-runs do not double-count or re-notify.

## Failed-job runbook

- List failures: `php artisan queue:failed`
- Retry one: `php artisan queue:retry {uuid}`
- Retry all: `php artisan queue:retry all`
- Delete one: `php artisan queue:forget {uuid}`
- Flush all: `php artisan queue:flush`

Notification failures do **not** roll back a successful registration — the registration row persists; the mail is retried from the `failed_jobs` table.

## PHPStan memory

`composer types:check` runs PHPStan with `--memory-limit=512M`. The default 128M crashes parallel workers on this codebase; the override lives in `composer.json`.
