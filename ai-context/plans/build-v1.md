# Build v1 (task plan)

> **Status (2026-11-22): slices 0, 1 and 2 built and tested locally.** Waiting on the product owner:
> host deployment + cloud probes (slice 0), the real two-agent trial (slice 1 task 6 / slice 2 task 6).
> Invite, @name addressing and the previewer filter were pulled forward from slice 3. `smoke.ps1`
> (slice 2 task 5) was replaced by in-process integration tests (`tests/integration/WebFlowTest.php`)
> plus a browser check. `ai-context/progress.md` is the authoritative record.

Normative references: everything in `docs/architecture/` and `docs/design/`. Exit criteria per slice
are in `docs/architecture/roadmap.md`; tasks and acceptance criteria are here. One branch per slice
(`feature/slice-0-probe`, …), merged on the product owner's word. Log actual hours beside each
estimate in `progress.md`.

**Settled (2026-11-22):** the product owner's hosting provider (MySQL) hosts it; MySQL locally too;
no tool list — the probe is generic. **The first goal is a demo at a friend's workplace** with
non-sensitive content, so favour a reliable, convincing end-to-end path over completeness.

---

## Slice 0 — Probe (est. 1–2 h)

Minimal skeleton plus the probe; nothing else. It must be deployable on its own.

1. **Skeleton.** `public/index.php`, `.htaccess`, `src/bootstrap.php`, `Config`, `Db`, `Router`,
   `Responses` (with the common headers), `config.default.php`, `config.local.php.example`,
   `robots.txt`. *Accept:* `php -S` serves `/` with a placeholder text; unknown path → 404 text;
   headers from http-api.md §1.5 present.
2. **`LenientQuery`** with its unit tests (implementation.md §Testing list) and `tests/run.php`.
   *Accept:* all parser cases pass; `php tests/run.php` exits non-zero on failure.
3. **Probe.** `probe_hits` table, `/probe`, `/probe/echo`, `/probe/log` with texts from agent-texts.md
   §9. *Accept:* the four probe steps work from a browser and PowerShell `Invoke-WebRequest`; the log
   page shows each hit with decoded message and query length.
4. **Host check**: confirm PHP ≥ 8.2 (`pdo_mysql`, `mbstring`), MySQL ≥ 8.0 / MariaDB ≥ 10.6,
   `.htaccess` rewrites, HTTPS (Q8 note). A `/probe` hit from a browser over HTTPS proves the stack.
5. **`how to run.txt`**: local MySQL setup, local run, deploy to the host, the probe prompt.
6. **Run the probe** with the product owner against whatever tools they have to hand, and ideally
   the demo audience's tools; fill `docs/design/agent-capabilities.md`. **Stop for go/no-go.**

## Slice 1 — Agent core (est. 4–6 h)

1. **Schema** (`db/schema.sql` = data-model.md) and `Keys`. *Accept:* schema loads on MySQL 8 (and
   MariaDB if available); key tests (alphabet, length, uniqueness over 10k).
2. **Domain:** `Conversations` (create, find, expired?), `Participants` (create, find by key, mark
   read/seen), `Messages` (post with seq allocation, dedupe, mentions; list after n; paging),
   `Activity`. *Accept:* unit/integration tests against a scratch DB, including two concurrent posts
   getting distinct consecutive seqs.
3. **`Texts` + `TranscriptFormatter`** implementing agent-texts.md §2–8 verbatim. *Accept:* golden
   tests on a fixed conversation (first-fetch briefing, later fetch, empty read, post, duplicate,
   each error).
4. **`AgentController`**: `/a/{key}`, `/read`, `/post` (GET+POST), `/help`, unknown sub-path → help,
   `format=html`, activity row per request in `finally`. *Accept:* http-api.md §2 behaviours.
5. **Dev script** `dev/create-board.php` that creates a conversation with two agents and prints their
   starter prompts (no human pages yet).
6. **Trial:** two VS Code Copilot chats talk through the board (roadmap exit criterion). Record what
   the agents did wrong in `progress.md`; revise agent-texts.md if needed (doc and `Texts.php` in the
   same commit).

## Slice 2 — Human pages (est. 4–6 h)

1. Layout, CSS, home + `POST /new` (with rate limit and purge). *Accept:* creates conversation, lead
   and human participants; redirects to watch page.
2. Watch page server render: header with expiry, participants panel (status/nudge, last seen, Copy
   starter prompt, Copy for agent, Remove), transcript, post box with Post as, add-assistant form,
   activity. *Accept:* human-pages.md, everything escaped (test with `<script>` in a message, name
   and about).
3. `watch.js`: polling `/updates`, appending, statuses, title count, copy buttons, Ctrl+Enter,
   confirm on delete/remove. CSP `default-src 'self'` holds (no inline script).
4. Actions: post / relay, add, remove, extend, delete. *Accept:* removed agent's link returns the
   removed error; delete removes all rows.
5. `tests/smoke.ps1` end to end. *Accept:* passes against a local instance.
6. **Trial:** the brief's scenario with the tiers the probe found.

## Slice 3 — Invite and polish (est. 3–4 h)

1. `/invite` (idempotent by name, system message). 
2. `@name` addressing end to end (stored, shown `→ name`, relay line names addressees).
3. Unfurler User-Agent filter on write endpoints.
4. Limits and their errors (security-and-limits.md §5); purge of conversations, activity trim, probe
   hits.
5. Deployment notes: HTTPS, access-log query-string guidance, `config.local.php`.
6. **Trial:** three agents complete a real research task. Revise agent-texts.md from evidence.
7. Mark ADRs Accepted (or supersede) to match what was built; update architecture-summary.md.
