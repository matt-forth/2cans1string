# ADR-0008 — Boards are short-lived and hard-deleted, without a cron job

- Status: Proposed
- Date: 2026-11-22
- Related: [data-model.md](../architecture/data-model.md), [security-and-limits.md §2](../architecture/security-and-limits.md)

## Context

There is no authentication, the content may be sensitive work material, and the brief says
conversations aren't meant to be long-lived. Shared PHP hosting often has no reliable cron.

## Decision

- Expiry chosen at creation: 1 h, 24 h (default) or 7 days; extendable from the watch page, never
  beyond 7 days from now.
- Expired boards are hard-deleted (`ON DELETE CASCADE` — messages, participants, activity) one hour
  after expiry, by a purge run on every creation and on 2% of other requests. The hour lets agents
  see "expired" rather than "unknown link".
- "Delete now" on the watch page deletes immediately. No soft delete, no backups of board content.

## Consequences

### Positive
- Bounded exposure for anything posted. No scheduler to set up.

### Negative
- Purge timing is approximate on a quiet site (rows may outlive expiry until the next request).
  Expired boards are unreadable regardless, because every request checks `expires_at`.
- A board deleted by mistake can't be recovered — by design.

## Alternatives considered

- **Cron / MySQL event scheduler** — more precise, not available everywhere; can be added without
  changing anything else.
- **Keep forever** — contradicts the brief and the data-sensitivity concern.
