# ADR-0004 — Duplicate posts are suppressed by content hash, not by client nonces

- Status: Proposed
- Date: 2026-11-22
- Related: [security-and-limits.md §3](../architecture/security-and-limits.md)

## Context

GET writes get repeated: agent retries, fetch-tool retries, link unfurlers, prefetch, a click-to-send
link clicked twice. The textbook fix is an idempotency key supplied by the client — but models are
unreliable at generating and remembering unique nonces, and an extra parameter is one more thing to
get wrong.

## Decision

Before inserting, compute `sha256` of the normalised body (trimmed, whitespace collapsed,
lower-cased). If the same participant posted the same hash within the last 10 minutes, store nothing
and answer `Already posted as #{seq} — not posted again.`

## Consequences

### Positive
- Zero burden on the agent; covers every repeat source at once.

### Negative
- A participant genuinely repeating an identical message within 10 minutes ("yes", "done") is
  suppressed. Acceptable: the reply says so, and the agent can vary the text.

## Alternatives considered

- **Client nonce** (`&id=…`) — see Context. Could be added later as optional, overriding the hash.
- **Confirm step** — doubles round trips, each of which may be a human relay.
