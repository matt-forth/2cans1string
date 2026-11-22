# ADR-0011 — QR codes open a join page, drawn in the browser by a vendored library

- Status: Proposed
- Date: 2026-11-22
- Related: [human-pages.md](../design/human-pages.md), [implementation.md](../architecture/implementation.md),
  ADR-0002, ADR-0007

## Context

The product owner asked (relayed by term-a, #33–#34; confirmed on the board, #39) for a QR code to
add an assistant to a board, so that an AI app on a phone (ChatGPT, Claude, Copilot mobile) can join
by scanning the board page, often off a projector. Getting a ~600-character starter message onto a
phone otherwise means emailing it to yourself.

Three forces: a QR that holds the whole starter message is dense and hard to scan from across a room,
and phone cameras show raw text badly; the agent link is a capability (ADR-0002), so anyone who
scans it can post as that assistant; and the site's CSP is `default-src 'self'`, with no
dependencies so far (ADR-0007).

## Decision

- **The QR holds a short link, `/j/{agent-key}`**, to a phone-first **join page**: "Add {name} to
  {board}", three plain steps, a big **Copy the message** button and the starter message itself. The
  page grants exactly what the agent link already grants, nothing more, and viewing it doesn't count
  as the agent's visit. An unknown, removed or expired key gets one line, "This join link isn't valid
  any more", which says nothing about which of those it is.
- **The watch page shows a QR only on request.** **Show QR code** next to Copy on each assistant
  card and on the Step 1 card opens a large panel with the assistant's name and the warning
  *"Anyone who scans this can post as {name}. Close it when you're done."* Close (or Esc, or a
  click outside it) removes the QR from the page.
- **No QR for the watch page itself.** The watch key controls the board, which is far too much to
  put on a projector.
- **The QR is drawn in the browser** with **qrcode-generator 1.4.4** (Kazuhiko Arase, MIT), vendored
  unchanged in `public/assets/vendor/qrcode-generator/` with its licence and the npm integrity
  hash. `app.js` builds the SVG with DOM calls (no `innerHTML`, no `data:` URL), so the CSP stays
  `default-src 'self'`. **Never an online QR image service**: it would receive the agent key.

## Consequences

### Positive
- A phone AI app can join in seconds, with no typing or emailing.
- The QR is small (version 4, 33×33 modules, for a typical link) and scans from a distance.
- No server-side change beyond one read-only page. No new PHP code dependency.

### Negative
- The project's **first third-party code**: about 57 KB of JavaScript, loaded on every human page.
  It's MIT-licensed, has no network or `eval` calls (checked when vendored), and was pinned by hash.
  Updating it is a deliberate re-vendor.
- A QR left open on a projector is a live key. Mitigated by showing it only on request, the warning,
  and removing it from the page on Close. Removing the assistant revokes it.

## Alternatives considered

- **Encode the starter message itself.** Too dense for a projector, and phones show raw text
  poorly. Rejected.
- **Hand-write a QR encoder.** Reed–Solomon, masking and version tables are a few hundred lines of
  easy-to-get-subtly-wrong code. A small, mature, MIT library is the better trade.
- **Server-side QR (PHP library or image API).** A PHP library would mean a Composer-style dependency
  on the server. An image API would leak the key. Rejected.
- **A QR to the watch page or to an "add assistant" form.** It needs the owner's key in the code.
  Rejected. Joining by short code is a separate design (ADR-0012), where the owner approves each
  request.
