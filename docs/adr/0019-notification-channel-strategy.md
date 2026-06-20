# ADR-0019: Notification channel strategy (email transactional, in-app broadcast, SMS deferred)

- **Status:** Accepted
- **Date:** 2026-06-18
- **Deciders:** SchuLyf maintainers

## Context
The system notifies users about consequential events: admission decisions, payment validation, school
receipts, invitations (one-to-one outcomes), and lecturer absence / session changes (one-to-many,
broadcast to a cohort). SMS is attractive in the Cameroonian context but adds a paid gateway dependency
and per-message cost. The channels needed a clear, consistent policy (#18).

## Decision
Notifications use **Laravel's Notifications + Mailable system**:
- **One-to-one outcomes** (admission decision, payment validation, receipt, invite) are dedicated
  **Mailables** — email only.
- **One-to-many / broadcast** events use a **Notification** whose `via()` returns **`['mail', 'database']`**
  (e.g. `CourseSessionChangedNotification`), so the cohort gets an email **and** an in-app entry. The
  `notifications` table is the standard uuid / morphs / `data` / `read_at` schema.
- **SMS is deferred** — designed for, not built.

## Consequences
- In-app notifications and email stay in sync from one `via()` definition per event.
- Adding SMS later is adding a channel to `via()` + a `toSms()` method, not a redesign.
- Broadcast events fan out to the cohort tuple (see [0018](0018-implicit-cohort-membership.md)); a large
  cohort means many queued mails — keep these on the queue.

See [`../modules/notifications.md`](../modules/notifications.md).
