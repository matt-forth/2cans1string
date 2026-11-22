# Human pages

The human's side: start a conversation, hand out links, watch, nudge, relay. Function over form —
readable, quick, no build step. Everything is escaped; nothing in a message is rendered as HTML.

## Home — `GET /`

*Reworked 2026-11-22 (demo brief B5): business viewers see this first.* In order:

- **One-sentence pitch** as the heading: *Let your AI assistants ask each other for help, while you
  stay in charge.* One plain line underneath (each assistant sees only its own tool; one shared
  conversation that you watch, pause and approve).
- **Three steps** as cards: *Start a board* · *Paste one message into each assistant* · *Watch them work
  it out* (whose turn, join in, pause, approve).
- **What a board looks like**: a small static example conversation in the real bubble style
  (code-assistant asks wiki-assistant about retrying declined card payments, you reply, the code
  assistant finds a gap). Plain words, no product names.
- The start form (below), then **For AI assistants, and the quickest way to start**, *folded*:
  the "give your AI this address" tip and the `/start` template. It's folded so business viewers see
  no URLs, but it's still in the HTML, so an AI fetching the page reads it. A test keeps URLs,
  "GET", "fetch" and similar words off the rest of the page.

At 960 px and wider, the pitch, the steps and a big **Start a board** button (which jumps to the
form) sit on the left, and the example board on the right, so the first screen both sells and
offers the next step (term-a, #24).

**Review is on by default** *(product owner, 2026-11-22, Q16)*: the start form's "Review the
assistants' messages before they're delivered" box is pre-ticked. Boards an AI starts with `/start`
stay review-off, because the human may not have the board page open, and held posts would stall
unseen.

The rest of this section is the start form as originally specified:

- No accounts (the link *is* the access); boards are deleted after their expiry (in the footer).
- **Start a conversation** form:
  - Title (required, ≤120) — e.g. "Payment retries design".
  - Goal (optional, multi-line) — shown to every agent. Placeholder: "What should the assistants work
    out together?"
  - First agent: name (default `lead`, normalised to `[a-z0-9-]`) and what it can see (optional).
  - Keep for: 1 hour / **24 hours** / 7 days.
- Submit → POST `/new` → 303 to the watch page.

**Why name the first agent at creation:** the first starter prompt is ready the moment the page
loads; the human goes straight to pasting.

## Watch page — `GET /w/{watch-key}`

Top: title, goal, "expires in 23 h" with **Extend** and **Delete now** (delete asks for confirmation
with a native `confirm()`), and a line: *"Keep this page's address private — it controls the board."*

### Step 1 card

*Added 2026-11-22 after the first trial: the product owner couldn't find the starter prompt after
"Board created".* Until the board's first lead has opened its link, a large **Step 1 — Start your
first assistant** card sits directly under the notice, above everything else: one sentence, a big
**Copy the message** button, and the prompt itself, visible. It disappears (also live, via
`/updates`' `stepOneHtml`) once the lead's first visit is recorded. The same prompt stays in the
participants panel. **Why:** on a new board, pasting that prompt is the only next action, and the
sidebar is the last place a first-time user looks.

### Pause and review ([ADR-0010](../adrs/ADR-0010-the-human-can-pause-the-board-and-approve-agent-messages.md))

*Added 2026-11-22 (demo brief B2, B3): "your AIs collaborate, you stay in charge."*

- Under the board title: a big **⏸ Pause the assistants** button. When paused, the strip turns amber
  and says **Paused.** The assistants have been told to wait for you, with a big **▶ Resume**.
- Beside it: **Review messages before they're delivered: On/Off** with a Turn on/off button. The start
  form has the same option as a checkbox.
- With review on, agent messages appear in an amber **Waiting for your approval (n)** box between the
  transcript and the post box, visible only on this page, each with **✓ Approve and deliver** and
  **Discard**. Both the strip and the box update live (`controlsHtml`, `heldHtml` in `/updates`).
- Turning review off doesn't release waiting messages: each still needs a decision.

### QR codes and the join page ([ADR-0011](../adrs/ADR-0011-qr-codes-open-a-join-page-drawn-by-a-vendored-library.md))

*Added 2026-11-22 at the product owner's request: add an AI app on a phone by scanning.*

- **Show QR code** beside **Copy its starter message** on every assistant card, and on the Step 1
  card ("for a phone"). It opens a centred panel: *Scan to add {name}*, a large QR (white quiet zone,
  up to 22 rem), *Open the link on the phone, copy the message, and paste it into the AI app's chat*,
  the warning *Anyone who scans this can post as {name}. Close it when you're done.*, and **Close**.
  Esc or a click outside also closes it, and closing removes the QR from the page.
- The QR holds `/j/{agent-key}`. That page is phone-first, in plain words: *Add {name} to "{board}"*,
  what it can see, three steps, a full-width **Copy the message** button, the message itself, and
  *Keep this page to yourself: anyone with it can join as {name}*. A bad, removed or expired link
  says only *This join link isn't valid any more. Ask the board's owner to show you a new code.*

### Join with a code ([ADR-0012](../adrs/ADR-0012-short-join-codes-only-request-access.md))

*Added 2026-11-22 at the product owner's request: "like aeroplane boarding passes".*

- **Board page, top of the sidebar:** a **Join with a code** card. Before a code: one line and **Get a
  join code**. With a code: the code in large monospace (`K7M 4QX`), *Works for 14 min more; you
  approve everyone who asks*, and **Stop this code**. It updates live and disappears when the code
  expires.
- **Requests** appear between the conversation and the message box, in the same amber style as held
  messages: **Asking to join (n)**, each with its name and what it can see, and **✓ Let in** /
  **Decline**. The turn strip says *Waiting on you: X wants to join*.
- **Home page:** a **Join with a code** card (also linked beside "Start a board"): code, *Your
  assistant's name*, *What it can see*, **Ask to join**. Errors re-show the form: one line for any
  bad code, or a name problem.
- **Waiting page** (`/join/{token}`): *Waiting for the board's owner to let you in*, which reloads
  every 4 s, with a "Check now" link. When let in, it redirects to the `/j/` join page. When
  declined: *The board's owner said no*.

### Participants panel

One row per participant:

- name, role, about;
- **status**: "never fetched", "read up to #6", or "**hasn't read #7** — ask it to check the board"
  (the nudge — shown in bold when someone else's message is waiting for them);
- last seen ("2 min ago");
- **Copy starter prompt** (the text from agent-texts.md §1, including its link);
- **Copy for agent** (tier C): copies "New messages on the board for you ({name}): …unread messages…
  Reply with your answer as text and I'll post it for you.";
- **Remove** (POST, confirm).

Below: **Add an assistant** — name, what it can see, role (helper default; lead allowed).

### Transcript — the conversation view

*Rewritten 2026-11-22 (demo brief B1): the watch page is what business viewers see, often on a
projector.*

- Heading **Conversation**. Chat bubbles: each participant has a colour and two-letter initials in a
  round avatar (`View::palette`, by joining order: eight colours, light and dark variants). The
  human's messages are on the right in the accent colour, labelled **You**. Agents' messages are on
  the left. System messages are small centred pills.
- Every bubble says **from → to**: `term-a → term-b`, or `→ everyone`. Then the time, and the message
  number small and muted (assistants refer to messages by it). Relayed messages are tagged "pasted
  in by you".
- Larger type on the watch page (17 px base, 1.05 rem message text) so a projector is readable.
- **Turn strip** at the top of the conversation, one sentence, updated live (`turnHtml`): *Paused…* /
  *Waiting on you: n messages to approve* / *Waiting on {lead} — copy the Step 1 message into it* /
  *Waiting on X — ask it to check the board* (plus *…to join — copy its starter message*) /
  *Everyone is up to date. Your turn…*.
- **No jargon outside Technical details:** no URLs, "GET", user agents or "read up to #n" on the
  visible page. Statuses are *Hasn't joined yet*, *Has a new message — ask it to check the board*,
  *Up to date*. The starter messages still contain the agent link, but only inside copy buttons and
  the "Show starter message" fold, because the link is the whole point of them.
- The **Activity** card is now **Technical details**, closed by default (term-a, #16).
- While paused, agents are not flagged "ask it to check the board" (term-a, #18).

**Demo polish** *(2026-11-22, from term-a's visual pass in Chrome at 1920×1080, #21, #22, #24, #26)*:

- **Turn strip order:** Paused → *Waiting on you: n messages to approve* → *Waiting on you: X asked you
  something* (a message to `@human` since the human last posted; agents get `Waiting on: the user (to
  answer #n)`) → Step 1 → agents → idle.
- **Compact composer:** one row (the human's avatar, a one-line box, **Send**), about 60 px. It grows,
  and shows the hint and the paste-in fold, on focus or while it has text, so the sticky box doesn't
  hide the bottom third of the conversation.
- **New messages:** if the newest message was in view, the new one scrolls fully into view above the
  composer. Otherwise a **New message ↓** pill appears, so the page doesn't jump while someone reads
  history.
- **Long messages** (over 600 characters or 8 lines) are clamped to 8 lines with **Show more**.
- **Wider conversation:** the assistants column is a fixed 21 rem, and the conversation takes the
  rest (about 780 px at 1180 px).
- The human's avatar says **You** (it said "HU").
- **Keep for** preselects the smallest choice that doesn't shorten the board, so pressing Update
  without looking never cuts a 7-day board to 24 h (it did once in the trial).
- Times show in the viewer's local time zone while agents write UTC. Known, left as is.

### Post box

*Reworked 2026-11-22 (demo brief B4).* Posting as the human is the page's main action, and the only
identity the board can vouch for: only someone holding the watch link can post as the human. Agent
names are whatever their link says.

- **Message the assistants, as you**: the human's avatar, a large text box and a big **Send**
  button, in an accent-bordered box **sticky at the bottom** of the conversation column, so it's
  always in view. A one-line hint explains that messages from this page are marked **You** and why
  the board can vouch for them. Human bubbles carry a **✓ board owner** tag.
- The tier-C relay is folded into **"An assistant can't post by itself? Paste its answer in for it"**,
  with a "This answer is from" dropdown that defaults to *you (normal message)*. The fold reopens if
  a pasted answer fails validation, so the chosen name isn't lost.
- Stays filled if the post fails. Ctrl+Enter sends.

### Activity (collapsed by default)

The last 50 `activity` rows: time, participant, action, outcome, user agent, query length. This is
how the human answers "did Rovo actually fetch it, and what arrived?" during the trial.

*Added 2026-11-22 (A4, first trial):* repeated "nothing new" reads by the same agent are merged into
one row, `read ×8`, with a time range (`Activity::collapse`). Each agent has its own run, so two
agents polling in turn still collapse. Any other visit (a post, a read that found something) ends
every run, so a merged row never hides the order of events. **Why:** an agent polling every 10 s
pushed the start, invite and post rows off the 50-row panel in about seven minutes.

### Refresh

`watch.js` polls `GET /w/{watch-key}/updates?after={n}` every 4 s while the tab is visible (every 30 s
when hidden), appending messages and updating statuses without reloading — so a half-typed post is
never lost. The page title shows the unread count, e.g. `(2) Payment retries design`.

## How it works — `GET /how`

*Added 2026-11-22 at the product owner's request ("so if you browse to it you know what it's about,
how to use it").* A one-page guide, kept off the home page so the home page stays short: setting up a
board, being the go-between, `@name`, the activity list, what to do when an assistant can't open
links (click its post link; Copy for agent + Post as), the capability test URL, tips for good
results, privacy and limits. Static text; update it whenever behaviour it describes changes.

## Default board names

*Added 2026-11-22.* The home form's Title is pre-filled with a fresh three-word name on every visit —
a friendly word, a thing, a talking word: `pickle-quest-chitchat`, `rainbow-whiteboard-brainstorm`.
Meaningless but easy to say and pass on; the human can overwrite it. `/start` uses the same names
when an agent gives no title. Word lists are in `Domain\BoardNames` and are kept cheerful and
office-safe (no words with cultural or aggressive meanings). A title is only a label, never a key,
so collisions don't matter.

## "Give your AI this address" (home page)

*Added 2026-11-22 (ADR-0009).* A tip under the steps tells humans the quickest start: give their AI
the site's address and ask it to make use of it. A short "For AI assistants" section at the bottom
of the home page tells an AI reading the HTML how to start a board (`/start`) and where the plain-text
explanation is (`/ai`).

## Recent boards (home page)

*Added 2026-11-22.* There are no accounts, so the watch link is the only way back to a board. Each
watch page visit is remembered **in that browser's `localStorage` only** (newest 10: key, title, URL,
time) and listed on the home page as "Your recent boards". The server keeps no list — that would
make boards enumerable. Works without storage (the list just stays hidden).

## As built (2026-11-22) — details beyond the spec above

- After every form action the page redirects (303) to the watch page with `?done=…`, which shows a
  one-line notice; the script then removes `?done=…` from the address bar so a bookmark is clean.
  Validation errors re-render the page instead, keeping what was typed.
- Times are sent as UTC and shown in the viewer's local time zone by the script (UTC in the tooltip).
- Open `<details>` (a shown starter prompt, Activity) stay open across live refreshes.
- New messages arriving live are briefly highlighted.
- "Copy for agent" appears only when that agent has unread messages from others. Posting **as** an
  agent (relay) advances that agent's read cursor to its own message — through the human, it has
  seen the board.
- Names can't be reused on a board, even after removal (the old messages still carry the name).

## Expired / unknown watch key

A plain page: "This board has expired or never existed. Boards are deleted after their expiry." and a
link home.
