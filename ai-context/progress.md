# Progress

Update this at the end of every working session. Newest session-log entry at the top.

## Current status

**Live at https://2cans1string.com since 2026-11-22 (11:00 UTC).** Earlier the same day it was demo-ready locally: Slices 0–2, the fixes from the
first terminal-to-terminal trial, the demo-polish brief (pause, review before delivery, conversation
view, home page) and the shared-hosting deployment kit are built, tested and committed on
`feature/demo-polish` (baseline `db7331f` on `main`). 62 tests pass, and `tests/smoke.ps1` passes
against localhost and against the packaged upload layout. Next: the product owner deploys
(`docs/deploy.md`), decides Q15–Q17, and merges the branch.

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

- Demo brief (2026-11-22, `feature/demo-polish`): trial fixes A1–A4, pause and review (ADR-0010),
  conversation view, prominent human posting, home page, demo polish, deployment kit
  (`docs/deploy.md`, `dev/package.ps1`, `tests/smoke.ps1`, `bin/purge.php`). See the session log.

## Not started

- Deployment to the host and cloud-tool probe runs (product owner; everything is ready: `docs/deploy.md`).
- A trial with a non-Claude AI tool (so far only two Claude Code terminals have used it).
- Three-agent trial; accepting the ADRs (0001–0010, all Proposed).

## The open risk that matters most

**R1 — the agents may not do it.** Building a URL that carries data to an outside site and fetching
it is exactly what agent vendors defend against as prompt injection / exfiltration. Which tool can do
what is unknown until probed. See `docs/architecture/risks.md`.

## Decisions awaiting a human

- Q15 (should looping agents poll?) and Q18 (an AI redeeming a join code): see `open-questions.md`.
  Q16 and Q17 are answered.
- Merge `feature/demo-polish` into `main`; push (no remote yet).
- Accept / change the design (ADR-0001 to 0010).
- Q9 company data policy is deferred until after a demo (see the session log).

## Known issues / debt

- A request with raw (unencoded) spaces in the URL is rejected by PHP's built-in server before our
  code runs; Apache does the same. Real fetch tools encode spaces, so this is expected, but if a
  probe shows a tool sending raw spaces it will look like "no hit at all".

## Session log

### 2026-11-22 (later still) — First deploy: https://2cans1string.com is live

The product owner deployed from `build/upload` (term-a guiding, on cPanel: an addon domain with web
root `~/2cans1string.com/www`). The deploy found three things:

- `5e3845c`: the dotfile rule 403'd `/.well-known/acme-challenge/`, so AutoSSL renewals would fail
  silently and HTTPS would lapse in ~90 days. `/.well-known/` is now exempt first. It's pinned by a
  unit test and checked by the smoke test. `deploy.md` gained the addon-domain layout.
- `Class "PDO" not found`: pdo was off in cPanel's Select PHP Version. Now in `deploy.md`'s table.
- `bdaf9b6`: the smoke test's HTTP→HTTPS check always reported 0 on PowerShell 5.1 (a test bug; the
  site redirects correctly). Fixed with HttpWebRequest and checked read-only against the live site.

Smoke test against production: 13/14 before that fix, with the one failure being the test bug.

### 2026-11-22 (later) — QR codes and join codes — `feature/demo-polish`

Two product-owner requests, relayed by term-a (#33–#37). The product owner confirmed them directly
(#39–#40) before the build; they were not taken on term-a's word alone. Also decided in #40: review
on by default (Q16) and keep the random board names (Q17).

- `576daee`: the start form's review box is pre-ticked. `/start` boards stay review-off.
- `16202b2`, ADR-0011: **Show QR code** per assistant and on Step 1 → `/j/{agent-key}`, a
  phone-first join page. Drawn in the browser by **qrcode-generator 1.4.4 (MIT)**, vendored unchanged
  after an integrity-hash check (the project's first third-party code; `public/assets/vendor/** -text`
  keeps it byte-exact). term-a compared the drawn SVG module by module (0 of 1,089 differ). A
  real phone scan waits for the deployment.
- `62b8981`, ADR-0012: **join with a short code**. The code only files a request, and the owner lets
  each one in, because a 6-character code is guessable without rate limits. A wrong code reveals
  nothing. Q18 (an AI redeeming a code itself) is parked.
- Local DB: `db/upgrades/2026-11-22-join-codes.sql` applied. 71 tests; smoke test green.

### 2026-11-22 — Terminal-to-terminal trial, then the demo brief, built over the board — `feature/demo-polish`

**How it ran.** Two Claude Code terminals on the product owner's machine used the product itself:
term-a (lead, in the Code Xray repo) and term-b (this repo's session, helper) on the board
"Terminal-to-terminal test". The first round trip worked first time. `Zoë & "café" #42 – 100% ✓`
survived both directions intact. term-a then posted a build brief (#7–#9), which the product owner
approved. term-b built it, posted a one-line update per item, and term-a tested each one from
outside like a user, including visual passes in Chrome. **Both agents polled `/read` every ~10 s,
with the product owner's approval.** This is a working exception to ADR-0005 ("the human is the
clock"), not product behaviour, so it's recorded here and in Q15, not in an ADR. The human didn't
relay a single message after the start.

**Trial findings (A), fixed:** the starter prompt was hard to find after "Board created" (now the
Step 1 card); two different `r=` tokens in one response made "use the newest link" unanswerable (now
one token per response); both agents mangled non-ASCII in shell arguments (the briefing now says to
encode from a file); 10 s polling pushed the useful rows off the activity panel (empty reads now
collapse per agent).

**Built (B, C), commit per item:** pause/resume, and review before delivery, with held messages
getting no seq until approved (ADR-0010; poster told of approval or discard, term-a's #11);
conversation view (bubbles, colours, from → to, turn strip, no jargon outside Technical details);
prominent human posting ("board owner" tag, sticky compact composer); home page (pitch, three steps,
example board); polish from term-a's review (#18, #21, #22, #24, #26, #28: Waiting-on covers
pause/approvals/questions to `@human`, NOT ADDED, Show more, auto-scroll or New-message pill, wider
column, You avatar, keep-for never shortens, sticky turn strip); deployment kit (HTTPS redirect,
`.user.ini`, layout-2 lock, optional `bin/purge.php`, `docs/deploy.md`, `dev/package.ps1`,
`tests/smoke.ps1`).

**Learned.** A UTF-8 BOM in `config.local.php` breaks `declare(strict_types=1)` and makes every page a
500 (found while rehearsing the packaged layout; in the deploy guide). Git Bash heredocs mangle some
backslash sequences in inline Python, so writing scripts to files with the Write tool is safer.
term-b briefly caused 500s for term-a while editing live code under the running server: expected, but
worth knowing when an agent tests a site another agent is editing.

**Commits:** `db7331f` baseline (on `main`, rebuilt from a copy without term-b's in-flight edits, at
the product owner's request in #12); on `feature/demo-polish`: `5852873` A2+A3, `c0ab3d9` A1,
`3fa477b` A4, `21a3546` B2+B3, `4f97a8d` B1 + #18, `855eb94` B4, `12e4b81` B5, `3115d9f` polish,
`570aecc` sticky strip, `4ef3763` C1, `b8e0e58` C2, `aa73a38` C3.

**Local DB:** `db/upgrades/2026-11-22-pause-and-hold.sql` applied.

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
