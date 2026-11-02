# EventFlow

Production-style event registration and check-in platform built with Laravel 13, Inertia.js, and React. Implementation is phased per `app-spec.md`.

## Status

**Batch 1 — Foundation** complete. Covers roles, organizers + team membership, event CRUD with policies, audit log, `Money` value object, deterministic seeders, public event listing, and a minimal admin UI for role/membership management.

Remaining batches: registration core · queues/notifications · waitlist · payments · tickets/e-receipts · check-in/reporting · hardening.

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

| Role            | Email                |
| --------------- | -------------------- |
| System admin    | admin@eventflow.test |
| Organizer owner | alice@acme.test      |
| Organizer staff | bob@acme.test        |
| Organizer owner | carla@luma.test      |
| Attendee        | dana@attendee.test   |

Seeded organizers: **Acme Events** (`acme-events`) and **Luma Collective** (`luma-collective`). Each has 8 demo events spanning draft, published, scheduled, sold-out, completed, cancelled, and one private event (access code `LEAD-2026`).

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

Current suite: **95+ tests** across unit, feature, and policy coverage.

## Architecture notes

- **Enums** (`app/Enums/`) carry state machines; `EventStatus::canTransitionTo()` is the source of truth for publish/cancel/archive transitions.
- **Policies** are attached to models via `#[UsePolicy]` attributes (Laravel 13).
- **Actions** (`app/Actions/Events/`) wrap mutations in a DB transaction and emit audit entries through `AuditLogger`.
- **Money** (`app/Support/Money.php`) is a readonly value object using integer minor units; floats are never used for currency.
- **Wayfinder** generates typed route helpers under `resources/js/actions/` and `resources/js/routes/`; frontend code should import from those rather than hard-coding URLs. Regenerate with `php artisan wayfinder:generate` after route changes.

## PHPStan memory

`composer types:check` runs PHPStan with `--memory-limit=512M`. The default 128M crashes parallel workers on this codebase; the override lives in `composer.json`.
