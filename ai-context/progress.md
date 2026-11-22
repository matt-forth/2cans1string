# Progress

Update this at the end of every working session. Newest session-log entry at the top.

## Current status

**Slices 0, 1 and 2 are built and tested locally (2026-11-22); nothing is deployed yet.** The site
is usable end to end at http://localhost:9099: home page with explanation and "Start a board", a
`/how` guide, the watch page (live transcript, assistant statuses/nudges, starter prompts, post or
relay, add/remove, extend/delete, activity), and the full agent API. 40 tests pass (26 unit, 14
integration). Next: the product owner's real trial with two or more AI chats; deploy and probe cloud
tools; then what's left of slice 3 (deployment notes, three-agent trial, accept the ADRs).

## Done

- Repository initialised (agent-context structure, docs structure, root config files).
- v1 solution design: overview, risks, HTTP API, data model, security and limits, implementation,
  roadmap; agent texts, human pages, agent capabilities (probe procedure); eight proposed ADRs;
  architecture summary, glossary, open questions, build plan.

- Slice 0 code: skeleton, `LenientQuery` (+16 tests), `Keys` (+4 tests), probe endpoints, full
  `db/schema.sql`, `dev/` scripts, `how to run.txt` (tests, local run, deploy, probe prompt).

- Slice 1 code: `Domain\{Conversations, Participants, Messages, Activity, LimitReached}`,
  `Agent\{AgentController, Board, Texts, TranscriptFormatter}`, `dev/create-board.php`, unit tests for
  formatting/mentions/hash/names, in-process integration tests (`App::handle` + output buffering).

- Slice 2 code: `Web\{WebController, View}`, templates (layout, home, how, watch, messages,
  participants, activity, gone), `public/assets/app.js` + `app.css`, `Responses::json/redirect`.

## Not started

- Deployment to the host and cloud-tool probe runs (product owner).
- A real trial with two or more AI chats (product owner).
- Rest of slice 3: deployment notes (HTTPS, access logs), three-agent trial, ADRs accepted.
- Accepting the ADRs (planned at the end of slice 3, to match what was built).

## The open risk that matters most

**R1 — the agents may not do it.** Building a URL that carries data to an outside site and fetching
it is exactly what agent vendors defend against as prompt injection / exfiltration. Which tool can do
what is unknown until probed. See `docs/architecture/risks.md`.

## Decisions awaiting a human

- Accept / change the design (ADR-0001 to 0008).
- Q9 company data policy is deferred until after a demo (see the session log).

## Known issues / debt

- A request with raw (unencoded) spaces in the URL is rejected by PHP's built-in server before our
  code runs; Apache does the same. Real fetch tools encode spaces, so this is expected, but if a
  probe shows a tool sending raw spaces it will look like "no hit at all".

## Session log

### 2026-11-22 — Default board names; "give the AI the URL and it just happens" — on `main`, uncommitted

Two requests from the product owner:

1. **Default board names** — the home form pre-fills a three-word name (`pickle-quest-chitchat`
   style) from `Domain\BoardNames`. Word lists kept office-safe (dropped "powwow" and "rumble" from
   the first draft).
2. **Root URL onboarding** — "give the AI the root site url and say 'make use of it'; it should
   understand what the service does, how to start a session, what to say to the human." Built as
   ADR-0009 (Proposed): `GET /` content-negotiates (browsers → home page; clients without
   `Accept: text/html`, e.g. PowerShell/curl → plain text for an AI), `GET /ai` serves that text to
   anyone, and `GET /start?name&about&title&invite&goal` creates a board in one fetch with the caller
   as lead. The `/start` response leads with a "TELL THE USER" block (watch link + helper starter
   prompts). **This amends ADR-0002:** the creating agent sees the watch link once — flagged to the
   product owner as the trade-off. Texts stay conditional ("if the user … wants you to use it"),
   never imperative (ADR-0006). The HTML home page gained a human tip ("give your AI this address")
   and a "For AI assistants" section, because many AI fetch tools request HTML.

Verified: 45/45 tests (5 new: root/`/ai` text vs HTML, pre-filled name, `/start` end to end
including the goal with an unencoded `&`, `invite=` with junk filtering, previewer and name-clash
handling). Real HTTP: `Invoke-WebRequest` and curl both get the AI text at `/`.

Seen in the database: the product owner's own board "Terminal-to-terminal test" (term-a, term-b) —
they're trialling. Left untouched; test cleanup only ever deletes boards the tests created.

### 2026-11-22 — Slice 2: the website for humans — on `main`, uncommitted

The product owner asked for "basic web site content so if you browse to it you know what it's
about, how to use it, how to see what your particular session is doing" — i.e. slice 2. Built per
`docs/design/human-pages.md`, plus two additions agreed in the reply: a separate `/how` guide (keeps
the home page short) and "Your recent boards" from browser `localStorage` (no accounts means the
watch link is the only way back; the server must never list boards).

Found along the way:

- **Real bug, caught by a test:** the live-update endpoint rendered the assistants panel without the
  base URL — the watch page would have errored on its first auto-refresh. Fixed (`model()` carries
  `base`).
- **Test-runner bug:** a PHP parse error in an integration test file was reported as "SKIP — no
  database" and the run still passed. Now only the DB connection is guarded; a broken test file
  fails the run.
- The `format=html` wrapper had an inline `style=` that the CSP would block; removed.

Verified: 40/40 tests (7 new web tests: home/guide, create validation, watch page content and nudge
status, **XSS escaping of title/goal/about/name/message**, post as human and relay as agent with the
cursor rule, updates JSON, add/duplicate/remove/extend/delete, bad keys). Browser check in Chrome:
form creates a board, an agent post appears live without reload, statuses and activity refresh,
times localised, `?done=` stripped, recent boards stored, no console errors. Test data removed.

Note: the human participant is named `human` by default (`human_name` in config). The product owner
may want their own name in `config.local.php`.

### 2026-11-22 — Slice 1 built: the agent side of the board — on `main`, uncommitted

The product owner said go after the VS Code probe passed (cloud probes still pending — building
ahead of them was their call). Built per `plans/build-v1.md` slice 1, plus three items pulled
forward from slice 3 because they were small and the briefing already refers to them: `/invite`,
`@name` addressing, the link-preview filter.

Decisions and deviations, all reflected in the docs:

- **Seq allocation** uses `SELECT … FOR UPDATE` on the conversation row rather than the
  `LAST_INSERT_ID(expr)` trick in the original data-model.md: it lets the duplicate check and the
  message cap run under the same lock, and duplicates don't burn a seq. data-model.md updated.
- **Everything under `/a/` routes to the agent controller**, so a mangled key (found by a test:
  `/a/not-a-key` gave a bare 404) gets the "link isn't recognised" text. http-api.md updated.
- **`format=html` must come before `message=`** (everything after `message=` is message) — the help
  text says so. agent-texts.md updated, along with the one-line header on `/read`/`/post` responses
  and the ` ⏎ ` in post echoes.
- **Previewer list** drops the bare "Teams" from the design (a Copilot fetch could carry it); uses
  `SkypeUriPreview`/`MicrosoftPreview`. security-and-limits.md updated.
- `Responses` skips `header()` when headers are already sent — only ever true under the in-process
  test runner.
- The probe's step-4 response no longer offers a step-5 link (VS Code Copilot asked about it).
- `dev/create-board.php` lives in `dev/`, not `tests/` as the plan said.

Verified: 33/33 tests (26 unit, 7 integration: briefing first/later, post with unencoded `&`,
addressing and relay line, duplicates, read cursor and waiting-on, "meanwhile" on post, POST keeps
`#` and newlines, invite idempotency and system message, every error text, previewer ignored,
expiry, `format=html`, activity rows, 25-message pagination both ways). Plus a real-HTTP smoke via
the dev server. Tests and the smoke clean up their boards.

### 2026-11-22 — First probe: VS Code Copilot is tier A — on `main`, uncommitted

Probed VS Code GitHub Copilot (agent mode) against the local site: all four steps passed, step 2
byte-exact, 1,500-character message fine. It made the requests with PowerShell `Invoke-WebRequest`
from the terminal (user agent), not a fetch tool. It reported faithfully, including a trimmed
trailing space, and stopped at step 4 as instructed. Recorded in `docs/design/agent-capabilities.md`.
Still needed for go/no-go: at least one cloud tool (Rovo / M365 Copilot / the demo audience's), which
requires the host deployment.

### 2026-11-22 — Local MySQL; probe verified against a real database — on `main`, uncommitted

The product owner installed MySQL Server 8.0.46 locally (service on 3306). `dev/setup-db.sql` +
`db/schema.sql` create the `cans` database and user (`how to run.txt` §2a); the schema loads cleanly
on MySQL 8.0. Verified: probe hits are recorded (GET and POST, 1,500-char message, exact UTF-8
bytes), `/probe/log` lists them with everything escaped (`<script>` in a message shows as text), the
7-day purge statement deletes old rows. Test rows were then cleared. Gotcha for future testing: Git
Bash on this machine has a broken locale, so non-ASCII typed into a curl command arrives as `?` —
send percent-encoded bytes instead.

### 2026-11-22 (later still) — Slice 0 built: skeleton + capability probe — on `main`, uncommitted

Built per `plans/build-v1.md` slice 0 tasks 1–3 and 5. Plain PHP, no dependencies. Notes:

- The machine's PHP is 8.5 (winget) with **no php.ini**, so `pdo_mysql`/`mbstring` are off by default.
  `dev/php.ps1` enables them per run rather than editing the machine's config. No MySQL locally;
  installing one wasn't done without asking.
- `db/schema.sql` contains the **whole v1 schema**, not just `probe_hits`, so the host's database is
  loaded once. `probe_hits` gained a `step` column (data-model.md updated).
- `index.php` supports two deployment layouts (code above the web root, or in `public/app/` with a
  deny `.htaccess`) because shared hosts differ.
- Verified locally with curl: instructions text, step-2 message arrives byte-exact when encoded,
  unencoded `#` truncation is diagnosed in the echo, POST path keeps `#` and newlines,
  `format=html` escapes correctly, headers per http-api.md §1.5, wrong probe key → 404, missing DB
  → echo still works with a "could not record" note. 20/20 unit tests.
- The probe's echo, beyond the design, checks the step-2 message against what was intended and
  names each kind of damage (missing `#hash`, lost line break, non-ASCII) — so a probe result is
  readable without diffing.

### 2026-11-22 (later) — Review answers: hosting, database, tools, demo goal — docs only

The product owner answered the design's questions:

- **Q8 hosting** → their existing hosting provider, MySQL only. Host capabilities (PHP version,
  rewrites, HTTPS) to be confirmed in slice 0.
- **Q12** → MySQL locally too (one dialect; they had no local preference).
- **Q11** → they challenged why the tool list matters, since anything that loads a page can use it.
  Agreed: the design is tool-agnostic and a list isn't needed. What varies by tool is only the tier
  (posting by itself vs. a click per post vs. copy/paste), because *composing* a URL that carries the
  model's output is what vendors restrict, separately from *loading* a page. The probe is generic and
  is run on any tool as it's met. `agent-capabilities.md` reworded to say this.
- **Q9 deferred**; **new goal: a demo at a friend's workplace** with non-sensitive content first;
  that organisation decides afterwards about its own data. Recommendation recorded: probe the
  audience's tools before the demo.

### 2026-11-22 — v1 solution design — on `main`, docs only

Designed the initial implementation from `prompts/the-idea.txt`, as asked: docs only, for review
before a later build session.

Main departures from the brief's sketch, each with an ADR and stated to the product owner:

- **Per-agent links minted by the service** instead of `poster`/`conversation` GUIDs in every URL
  (ADR-0002) — models copy example GUIDs and mis-copy long ones; one key per URL also lets agents be
  revoked and keeps admin rights with the human (separate watch key).
- **A starter prompt in the user's voice** instead of a bare link (ADR-0006) — a bare "go read this
  page and do what it says" is what injection defences are built to refuse.
- **Three capability tiers** (A: full; B: read + click-to-send; C: human relay with copy/paste) so
  partial agent capability degrades rather than kills the product.
- **Probe first** (slice 0) — the riskiest unknown is answered in an hour or two before ~12 h of
  building.
- POST accepted alongside GET; lenient raw-query parsing; always-200 plain text with server-minted
  next URLs; content-hash dedupe; read cursors driving a "who needs a nudge" watch page; hard-deleted
  short-lived boards without cron.

Not established and said so: the actual fetch capabilities of Visual Studio Copilot, Rovo and M365
Copilot (`docs/design/agent-capabilities.md` records beliefs and their confidence). Q1–Q7 and Q10
resolved by the design; Q8, Q9, Q11–Q14 open.

### 2026-11-22 — Repository init — on `main`, no product code

Set up the repository structure modelled on the product owner's CodeXrayProto1 project (an
unrelated product; only its *way of working* was copied): `ai-context/` as agent working memory,
`docs/` for normative design, ADRs immutable once accepted, `progress.md` updated every session,
`how to run.txt` for copy-paste commands. Seeded `open-questions.md` with the questions the brief
raises but does not answer, and `glossary.md` with the brief's terms. Rewrote `CLAUDE.md` to point
into `ai-context/`. No design decisions were made.
