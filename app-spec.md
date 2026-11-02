# EventFlow App - Implementation Specification

## 1. Purpose

EventFlow is a production-style event registration and check-in platform built with Laravel, Inertia.js, and React. It should demonstrate more than basic CRUD by including concurrency-safe capacity management, waitlists, queued processing, payment records, downloadable e-receipts, QR check-in, reporting, automated tests, and operational safeguards.

This document is the implementation source of truth for the coding agent. Implement the application incrementally and keep every completed phase deployable and tested.

## 2. Primary Goals

- Allow organizers to create, publish, manage, and report on events.
- Allow attendees to register for free or paid events.
- Prevent registrations from exceeding event or ticket-type capacity.
- Automatically waitlist attendees when capacity is reached.
- Promote waitlisted attendees when seats become available.
- Process slow or retryable operations through Laravel queues.
- Generate, store, download, print, and email electronic receipts.
- Provide QR-based attendee check-in with duplicate-scan protection.
- Maintain a reliable audit trail for important business actions.
- Include unit, feature, integration, concurrency, frontend, and end-to-end tests.

## 3. Technology Stack

### Required

- Laravel
- PHP
- Inertia.js
- React
- MySQL or PostgreSQL
- Redis for queues, cache, distributed locks, and rate limiting
- Laravel queue workers
- Laravel scheduler
- Vite
- PHPUnit or Pest for backend tests
- React Testing Library and Vitest for frontend tests

### Recommended

- Docker Compose for local development
- Laravel Horizon for Redis queue monitoring
- Playwright for browser-level tests
- S3-compatible storage for generated receipts and exports
- A PDF renderer behind an application interface
- A mail sandbox for local development

Do not couple the core domain directly to a specific payment or PDF vendor. Use interfaces and adapters so providers can be replaced.

## 4. User Roles

### System Administrator

- Manage users and organizer accounts.
- View all events and registrations.
- Investigate failed jobs and operational problems.
- View system-wide reports and audit logs.
- Suspend abusive or compromised accounts.

### Organizer

- Create and manage events.
- Configure ticket types, prices, capacities, registration windows, and waitlists.
- Invite staff members.
- View registrations and payment information.
- Export attendee, payment, and receipt reports.
- Cancel or reschedule events.

### Event Staff

- View assigned events.
- Search attendees.
- Scan QR tickets and perform manual check-in.
- Reverse an accidental check-in when authorized.
- View limited operational statistics.

### Attendee

- Browse published events.
- Register for an event.
- Join a waitlist.
- View registration status.
- Download tickets and e-receipts.
- Cancel a registration when permitted.
- Update permitted profile information.

Use Laravel policies for authorization. Do not rely only on hiding buttons in React.

## 5. Core Modules

## 5.1 Authentication and User Profiles

- Registration, login, logout, password reset, and email verification.
- Profile details and contact information.
- Role and permission management.
- Account status: active, suspended, or disabled.
- Optional multi-factor authentication.
- Record last login and relevant security activity.

## 5.2 Organizer and Team Management

- Organizer profile containing name, contact details, branding, and receipt information.
- Organizer owner and staff membership.
- Invitations with expiration dates.
- Event-specific staff assignment.
- Policies restricting organizers to their own events and data.

Design the data model so organizer scoping is explicit. Avoid trusting organizer identifiers submitted by the browser.

## 5.3 Event Management

Each event should support:

- Title, slug, description, category, and cover image.
- Organizer and assigned staff.
- Venue or online-event information.
- Start and end date/time with a stored timezone.
- Registration opening and closing date/time.
- Draft, scheduled, published, sold-out, completed, cancelled, and archived states.
- Overall capacity.
- Per-attendee registration limits.
- Waitlist configuration.
- Cancellation and refund policy text.
- Custom attendee instructions.
- Public or private visibility.
- Optional access code for private events.

Event publication must validate that required details, ticket types, dates, and capacity settings are valid.

## 5.4 Venue and Session Management

- Physical venue name, address, room, and map link.
- Online meeting URL stored securely and disclosed only to eligible attendees.
- Optional event sessions or agenda items.
- Session title, speaker, description, start/end time, and room.
- Optional per-session capacity and registration.

Sessions can be implemented after the main event registration workflow is stable.

## 5.5 Ticket Types

Examples include General Admission, VIP, Student, Early Bird, and Complimentary.

Each ticket type should support:

- Name and description.
- Free or paid classification.
- Price and currency.
- Capacity allocation.
- Minimum and maximum quantity per registration.
- Sales opening and closing dates.
- Active/inactive state.
- Display order.
- Optional attendee eligibility rules.

Store monetary values as integer minor units. Never use floating-point values for money.

## 5.6 Registration and Capacity Management

Registration should support:

- One attendee per registration initially, with an extensible design for group registration.
- Unique registration reference.
- Ticket-type selection.
- Required attendee information.
- Optional event-specific custom questions.
- Terms and policy acceptance timestamp.
- Status transitions with an audit trail.

Suggested registration states:

- `pending`
- `payment_pending`
- `confirmed`
- `waitlisted`
- `cancelled`
- `expired`
- `refunded`
- `checked_in`

### Capacity Safety

- Perform capacity checks and seat reservation inside a database transaction.
- Use appropriate row-level locking or an equally safe concurrency strategy.
- Never calculate availability only in the browser.
- Add idempotency protection to the registration submission.
- Prevent duplicate active registrations for the same attendee and ticket type when required.
- Expire abandoned payment-pending reservations through a scheduled command.
- Release expired or cancelled capacity before promoting waitlisted attendees.

Add a concurrency test proving that two simultaneous requests cannot claim the final available seat.

## 5.7 Waitlist Management

- Automatically add attendees to the waitlist when permitted and capacity is unavailable.
- Maintain a deterministic ordering, normally first-in, first-out.
- Allow organizer-defined priority only when explicitly enabled and audited.
- Promote the next eligible attendee when capacity becomes available.
- For paid tickets, create a time-limited offer instead of immediately confirming.
- Expire unaccepted offers and promote the next attendee.
- Notify attendees when they join, are promoted, or lose an expired offer.

Waitlist promotion must be concurrency-safe and idempotent.

## 5.8 Payment Module

The initial implementation may use a fake or sandbox payment adapter. Keep the domain independent of the provider.

Define a `PaymentGateway` interface supporting:

- Create checkout or payment session.
- Retrieve payment status.
- Process or record refunds.
- Verify and interpret provider webhooks.

Store:

- Internal payment reference.
- Provider and provider reference.
- Registration reference.
- Amount, currency, and fee information.
- Pending, paid, failed, cancelled, partially refunded, and refunded states.
- Payment method summary when safely provided.
- Timestamps and raw provider event identifier.

### Webhook Requirements

- Verify webhook signatures.
- Store incoming webhook events before processing.
- Prevent duplicate processing using the provider event identifier.
- Process webhook events through a queue.
- Handle out-of-order events safely.
- Retain failure information and support authorized replay.
- Never expose payment secrets to React or client-side code.

## 5.9 E-Receipt Module

Generate an e-receipt after a successful payment. Free registrations may receive a zero-value registration confirmation instead of a financial receipt.

Each receipt should contain:

- Unique, non-sequential public receipt number.
- Organizer name and contact information.
- Event title, date, time, and venue.
- Attendee name and email.
- Registration reference.
- Ticket type and quantity.
- Line items.
- Subtotal, discounts, taxes when applicable, fees, and total.
- Currency.
- Payment reference and payment date.
- Receipt issue date.
- Receipt status: issued, voided, partially refunded, or refunded.
- Refund or adjustment information when applicable.
- QR code or verification URL.

### Receipt Features

- Generate receipt content from an immutable receipt snapshot, not live event data.
- Render a PDF asynchronously.
- Store the generated PDF in private storage.
- Provide an authenticated download endpoint.
- Optionally provide a short-lived signed download URL.
- Allow printing from a browser-friendly receipt page.
- Allow organizers to resend the receipt email.
- Allow attendees to download receipts from their account.
- Regenerate only when authorized and record the reason.
- Generate a credit/refund receipt when money is refunded.
- Never silently modify an already-issued receipt.

### Receipt Exports

- Individual PDF download.
- Batch ZIP export for an event, generated through a queue.
- CSV receipt register containing receipt number, registration reference, attendee, issue date, status, currency, subtotal, tax, discount, and total.
- Organizer-selectable date and status filters.
- Export progress and completion notification.
- Store exports temporarily and delete them through a scheduled cleanup job.

## 5.10 Ticket and QR Code Module

- Generate a unique ticket token after confirmation.
- Generate QR codes asynchronously when useful.
- QR data must use an opaque token or signed URL, not a sequential database identifier.
- Allow the attendee to download or display the ticket.
- Revoke a ticket after cancellation or refund when appropriate.
- Support ticket reissue with invalidation of the previous token.

## 5.11 Check-In Module

- QR scanner interface optimized for mobile browsers.
- Manual search by registration reference, name, or email.
- Verify event, registration, ticket status, and check-in eligibility.
- Prevent duplicate check-in.
- Record staff member, timestamp, and check-in method.
- Allow authorized reversal with a required reason.
- Display clear success, warning, duplicate, cancelled, and invalid-ticket states.
- Continue to work gracefully under slow network conditions.

Optional advanced feature: an offline-tolerant check-in queue with later synchronization and conflict reporting.

## 5.12 Notifications Module

Support email notifications for:

- Email verification.
- Registration received.
- Payment pending.
- Registration confirmed.
- Waitlist joined.
- Waitlist offer available.
- Waitlist offer expiring.
- Registration cancelled.
- Payment failed.
- Refund completed.
- Ticket issued.
- E-receipt issued.
- Event reminder.
- Event update, reschedule, or cancellation.

All non-critical notification delivery should be queued. Notification failures must not roll back a successful registration or payment.

## 5.13 Reports and Exports

Organizer dashboard metrics:

- Total registrations.
- Confirmed, pending, waitlisted, cancelled, and checked-in counts.
- Capacity used and remaining.
- Registration trend over time.
- Revenue, refunds, discounts, and net collected amounts.
- Ticket-type performance.
- Check-in rate.

Exports:

- Attendee CSV.
- Registration CSV.
- Check-in CSV.
- Payment reconciliation CSV.
- Receipt register CSV.
- Batch e-receipt ZIP.

Large exports must run through queues and provide downloadable results when complete.

## 5.14 Audit Log

Audit at least the following:

- Event publication, cancellation, and rescheduling.
- Capacity and ticket-price changes.
- Registration status changes.
- Manual registration changes.
- Waitlist priority changes.
- Payment and refund actions.
- Receipt regeneration or voiding.
- Ticket reissue.
- Check-in reversal.
- Role and permission changes.

Each entry should include actor, action, subject type/id, before/after data where appropriate, timestamp, and request metadata. Do not store secrets or complete payment payloads in audit logs.

## 6. Queue Architecture

Use Redis-backed Laravel queues. Separate queues by workload when practical:

- `critical`: payment and webhook processing.
- `registrations`: registration finalization and waitlist promotion.
- `notifications`: email and other notifications.
- `documents`: receipt and ticket PDF generation.
- `exports`: CSV and ZIP exports.
- `default`: ordinary background tasks.

Suggested jobs:

- `ProcessPaymentWebhook`
- `FinalizePaidRegistration`
- `PromoteWaitlistedAttendee`
- `ExpirePendingRegistration`
- `ExpireWaitlistOffer`
- `GenerateTicketQrCode`
- `GenerateReceiptPdf`
- `EmailReceipt`
- `SendRegistrationConfirmation`
- `SendEventReminder`
- `GenerateAttendeeExport`
- `GenerateReceiptRegisterExport`
- `GenerateReceiptZipExport`
- `DeleteExpiredExportFiles`

### Queue Rules

- Dispatch jobs only after the surrounding database transaction commits.
- Make jobs idempotent.
- Use unique jobs or distributed locks for single-run workflows.
- Configure timeouts, retry counts, and exponential backoff intentionally.
- Keep external network calls out of database transactions.
- Record enough context to investigate failures without leaking secrets.
- Use Laravel failed jobs and provide operational retry procedures.
- Monitor queue depth and failures through Horizon or equivalent tooling.

## 7. Scheduled Tasks

Use Laravel Scheduler for:

- Expiring abandoned registrations.
- Expiring waitlist offers.
- Promoting eligible waitlisted attendees.
- Sending event reminders.
- Marking events completed after their end time.
- Cleaning temporary exports and expired signed resources.
- Retrying or reporting recoverable operational failures.
- Producing daily organizer summaries when enabled.

Scheduled commands must be safe to run more than once.

## 8. Suggested Data Model

Core tables:

- `users`
- `organizers`
- `organizer_user`
- `event_staff`
- `events`
- `venues`
- `event_sessions`
- `ticket_types`
- `registrations`
- `registration_attendees`
- `registration_answers`
- `seat_reservations`
- `waitlist_entries`
- `waitlist_offers`
- `payments`
- `payment_events`
- `refunds`
- `receipts`
- `receipt_items`
- `tickets`
- `check_ins`
- `exports`
- `audit_logs`
- Laravel queue, cache, session, notification, and failed-job tables as needed

Important constraints and indexes:

- Unique event slug.
- Unique registration reference.
- Unique provider event identifier.
- Unique receipt number.
- Unique active ticket token.
- Appropriate composite indexes for organizer/event/status/date queries.
- Database constraints preventing impossible quantities and invalid relationships.

## 9. Inertia and React Pages

### Public

- Event listing.
- Event details.
- Ticket selection.
- Registration form.
- Checkout/payment status.
- Registration confirmation.
- Waitlist confirmation.
- Public receipt verification page with limited information.

### Attendee Account

- Dashboard.
- My registrations.
- Registration details.
- Ticket display/download.
- Receipt display/download.
- Cancellation request.
- Profile settings.

### Organizer

- Dashboard.
- Event list and editor.
- Ticket-type manager.
- Registration list and details.
- Waitlist manager.
- Check-in dashboard.
- Payment and refund view.
- Receipt register.
- Reports and export history.
- Team and staff management.
- Organizer settings.

### Staff

- Assigned event list.
- QR scanner.
- Attendee search.
- Check-in history.

Use server-side authorization and validation. Return only the data each page requires through Inertia props.

## 10. Backend Design Conventions

- Keep controllers thin.
- Use Form Request classes for validation and authorization where appropriate.
- Use policies for resource authorization.
- Encapsulate business workflows in clearly named action or service classes.
- Use domain events to signal completed business actions.
- Use queued listeners only for work that can occur after the transaction commits.
- Use enums or equivalent typed states for important statuses.
- Use value objects for money and other error-prone concepts when they improve clarity.
- Use interfaces for payment, PDF, storage, and notification providers.
- Avoid a generic repository layer unless it provides real value.
- Document architectural decisions in `docs/adr/`.

Suggested actions:

- `PublishEvent`
- `CreateRegistration`
- `ReserveCapacity`
- `ConfirmRegistration`
- `CancelRegistration`
- `JoinWaitlist`
- `PromoteWaitlistEntry`
- `RecordSuccessfulPayment`
- `RefundPayment`
- `IssueReceipt`
- `CheckInAttendee`

## 11. Testing Requirements

## 11.1 Unit Tests

- Event publication rules.
- Registration state transitions.
- Capacity calculations.
- Ticket sales-window rules.
- Waitlist ordering and eligibility.
- Waitlist offer expiration.
- Money and receipt-total calculations.
- Refund eligibility and calculations.
- Receipt status transitions.
- Check-in eligibility.

## 11.2 Feature Tests

- Authentication and email verification.
- Organizer and staff authorization.
- Event creation and publication.
- Ticket-type management.
- Free registration.
- Paid registration initialization.
- Waitlist enrollment and promotion.
- Registration cancellation.
- Receipt download authorization.
- QR and manual check-in.
- Duplicate check-in prevention.
- Report filters and export requests.

## 11.3 Integration Tests

- Database transaction and locking behavior.
- Redis queue dispatch and processing.
- Payment adapter using a sandbox or local fake server.
- Signed payment webhooks.
- Duplicate and out-of-order webhook events.
- Receipt PDF rendering and private storage.
- Email delivery through a test mail service.
- CSV and ZIP export generation.
- Scheduler-driven expiration and reminder workflows.

## 11.4 Concurrency Tests

- Two users competing for the final seat.
- Two workers attempting to promote the same waitlist entry.
- Duplicate payment webhook delivery.
- Duplicate QR check-in requests.
- Repeated receipt-generation requests.

## 11.5 Frontend Tests

- Registration form validation and error display.
- Ticket selection and total calculation display.
- Organizer event form behavior.
- Status badges and permitted actions.
- QR scanner result states.
- Export progress and completion states.

## 11.6 End-to-End Tests

At minimum:

1. Organizer creates and publishes an event.
2. Attendee registers for a free event and receives a ticket.
3. Attendee completes a simulated paid registration and downloads an e-receipt.
4. Event reaches capacity and the next attendee joins the waitlist.
5. A cancellation releases capacity and promotes the next attendee.
6. Staff scans a valid ticket and duplicate scanning is rejected.
7. Organizer generates attendee and receipt exports.

## 12. Security and Privacy

- Protect all state-changing routes with CSRF protection.
- Rate-limit authentication, registration, payment, receipt verification, and check-in endpoints.
- Validate uploaded files and restrict their storage visibility.
- Keep payment secrets and webhook secrets server-side.
- Use signed or authenticated receipt and ticket downloads.
- Use opaque public identifiers instead of exposing sequential IDs.
- Escape user content and prevent unsafe HTML rendering.
- Avoid logging personal information, credentials, complete webhook payloads, or payment secrets.
- Apply least-privilege authorization to organizer and staff accounts.
- Record security-sensitive actions in the audit log.

## 13. Observability and Operations

- Structured application logs with request and registration correlation identifiers.
- Queue failure and retry visibility.
- Payment webhook processing status.
- Export generation status.
- Health endpoint covering application, database, Redis, and queue connectivity.
- Metrics for registrations, queue depth, failed jobs, webhook failures, and check-in throughput.
- Clear runbook for failed payment webhooks and stuck jobs.

## 14. Seed and Demo Data

Provide deterministic seeders for:

- System administrator.
- Organizer and staff accounts.
- Several upcoming, sold-out, completed, and cancelled events.
- Free and paid ticket types.
- Confirmed, waitlisted, cancelled, refunded, and checked-in registrations.
- Example payments and receipts.
- Demo credentials documented in the README for local use only.

## 15. Implementation Phases

### Phase 1 - Foundation

- Laravel, Inertia, and React setup.
- Authentication and profiles.
- Organizer/team model.
- Event CRUD, policies, factories, and seeders.
- CI running formatting, static analysis, backend tests, and frontend tests.

### Phase 2 - Registration Core

- Ticket types.
- Registration form and validation.
- Capacity reservation with transactions and locking.
- Free registration confirmation.
- Unit, feature, and concurrency tests.

### Phase 3 - Queues and Notifications

- Redis queues and Horizon.
- Confirmation jobs and emails.
- Scheduled expiration and reminders.
- Failed-job handling and operational documentation.

### Phase 4 - Waitlist

- Waitlist entries and promotion.
- Time-limited paid-ticket offers.
- Concurrency-safe promotion.
- Notifications and tests.

### Phase 5 - Payments and Webhooks

- Payment gateway interface and fake/sandbox adapter.
- Payment records.
- Webhook inbox, signature verification, idempotency, and replay.
- Refund workflow.
- Integration tests.

### Phase 6 - Tickets, E-Receipts, and Exports

- QR tickets.
- Receipt snapshots and numbering.
- Queued PDF generation.
- Authenticated downloads and email delivery.
- CSV receipt register and batch ZIP export.
- Cleanup schedule and tests.

### Phase 7 - Check-In and Reporting

- Mobile-friendly QR scanner.
- Manual check-in and reversal.
- Organizer dashboard metrics.
- Attendee, payment, receipt, and check-in exports.

### Phase 8 - Hardening

- End-to-end tests.
- Load and concurrency testing.
- Accessibility review.
- Security review.
- Performance profiling and query optimization.
- Deployment and operational documentation.

## 16. Definition of Done

A feature is complete only when:

- Authorization and validation are implemented.
- Relevant unit, feature, and integration tests pass.
- Queue jobs are idempotent and have defined retry behavior.
- Database constraints and indexes are included.
- User-visible errors are clear and recoverable.
- Logs do not expose secrets or unnecessary personal data.
- React pages handle loading, empty, success, and error states.
- Documentation and seed data are updated.
- CI passes.

## 17. Repository Deliverables

- Complete application source.
- `README.md` with setup, architecture, credentials, screenshots, and test commands.
- `.env.example` without secrets.
- Docker Compose configuration when used.
- Database diagram.
- Registration, payment, waitlist, receipt, and check-in sequence diagrams.
- API/webhook documentation.
- Architecture decision records.
- Factories and seeders.
- CI workflow.
- Automated test suite.
- Sample generated ticket and e-receipt using fake data.
- Deployment and queue-worker instructions.

## 18. Scope Guidance

Prioritize correctness and demonstrable engineering quality over feature count. Complete the free-registration workflow, capacity safety, queues, waitlist, e-receipts, check-in, and tests before adding optional features such as session registration, offline check-in, advanced taxation, multiple payment providers, or complex group bookings.
