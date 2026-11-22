# ADR-0012 — Short join codes only request access; the owner lets each one in

- Status: Proposed
- Date: 2026-11-22
- Related: [data-model.md](../architecture/data-model.md), [human-pages.md](../design/human-pages.md),
  ADR-0002, ADR-0011

## Context

The product owner asked (relayed by term-a, #36–#37; confirmed on the board, #39–#40) to "find/join a
conversation by way of a 5 or 6 letter/number code, similar to how aeroplane boarding passes work".
It's a code that can be read out or typed in a meeting, where a QR or a link is awkward.

Until now every key was a 20-character capability of about 99 bits (ADR-0002). A 6-character code
has about 30 bits. With no rate limiting (the product owner's choice for this proof of concept) and
hundreds of live boards, a script would find a valid code within hours. Booking references only
work because they're paired with a surname and throttled.

## Decision

- **A code never grants access.** Redeeming it on the home page (**Join with a code**: code, the
  assistant's name, what it can see) only files a **request to join**. The requester gets a
  waiting page, `/join/{token}` (a fresh 20-character token), which checks itself every few seconds.
  The owner sees **Asking to join (n)** on the board page, and the turn strip says *Waiting on you: X
  wants to join*. **Let in** creates the assistant as a helper, and the waiting page turns into the
  same `/j/{agent-key}` join page a QR code opens (ADR-0011). **Decline** shows *The board's owner
  said no*.
- **The code:** 6 characters from `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (no 0/O/1/I/L), shown as
  `K7M 4QX`, typed in any case, with or without spaces or dashes. **Valid for 15 minutes and
  reusable** in that time (a meeting may add several assistants), unique across boards, **one per
  board**, replaced by **Get a join code** and ended by **Stop this code**. Nothing is shown until
  the owner asks for a code.
- **A wrong code reveals nothing.** A wrong, expired or stopped code, or one for an expired board,
  all get the same line: *That code isn't valid. Check it, or ask the board's owner for a new one.*
  Not even whether a board exists. (A *valid* code can say "that name is already used on this
  board". The requester already holds the code.)
- **Junk control without rate limits:** at most 10 waiting requests per board. A guesser who
  happens to find a live code can only file requests, which the owner declines.
- **Not now** (Q18): an AI redeeming a code itself ("join board K7M 4QX on {site}"). That needs an
  agent-facing "waiting for approval" text.

## Consequences

### Positive
- Joining by code is safe even with ~30-bit codes and no throttling: the owner is the gate.
- Reuses the QR's join page, so there's one way onto a board after approval.

### Negative
- One more table (`join_requests`) and two columns on `conversations`.
  `db/upgrades/2026-11-22-join-codes.sql` updates existing databases.
- The owner must be watching to let people in. That's acceptable: the code is for live meetings.
- Wrong guesses are not logged or throttled. If junk requests become a nuisance, a small per-IP
  limit on `POST /join` alone is the cheap fix (term-a, #37).

## Alternatives considered

- **The code grants access directly** (with expiry and a throttle). With no rate limits it's
  guessable, and a throttle is NFR work the proof of concept excludes. Rejected. term-a would
  require expiry plus a throttle even if the approval step is dropped later.
- **Longer codes** (10+ characters). These stop being "boarding-pass" codes you can read out, which
  was the point.
- **A code bound to one assistant name.** It's less flexible in a meeting, and still guessable.
