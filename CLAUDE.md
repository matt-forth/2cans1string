# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Start here

This repository keeps its agent guidance in [`ai-context/`](ai-context/README.md). At the start of a
session read, in order:

1. [`ai-context/resume.md`](ai-context/resume.md) — current state and the next action.
2. [`ai-context/progress.md`](ai-context/progress.md) — what's done, and the session log (newest first).
3. [`prompts/the-idea.txt`](prompts/the-idea.txt) — the brief, if the task touches design.

New to the repo entirely? [`ai-context/HANDOFF.md`](ai-context/HANDOFF.md) is the self-contained brief.

## Project status

**Slices 0 (probe), 1 (agent API) and 2 (human pages: home, `/how`, watch page) built and tested
locally (2026-11-22). Nothing deployed yet.** Web code: `src/Web/` (controller, `View`, PHP templates
— escape everything with `e()`), `public/assets/app.js` (the only script; no inline script, CSP). Tests: `tests/unit` (pure) and
`tests/integration` (in-process via `App::handle`, against local MySQL; each test cleans up its board). Design: `docs/architecture/`, `docs/design/`, `docs/adrs/` (Proposed), compressed in
`ai-context/architecture-summary.md`. Build plan: `ai-context/plans/build-v1.md`. Stack: plain PHP
8.2+, no framework/Composer, MySQL via PDO.

## Commands

```powershell
.\dev\php.ps1 tests\run.php      # unit tests (exit 1 on failure)
.\dev\serve.ps1                  # http://localhost:9099 (needs config.local.php). Never 8080: another project owns it.
.\dev\php.ps1 -l <file>          # lint
```

Always go through `dev\php.ps1`: the machine's PHP (8.5, winget) has no php.ini, so `pdo_mysql` and
`mbstring` are only enabled by that script. Local MySQL 8.0 runs as a Windows service on 3306;
database and user `cans` / `cans-local-dev` (recreate: `how to run.txt` §2a). The `mysql.exe` client
is the one in `C:\Program Files\MySQL\MySQL Workbench 8.0\`. Git Bash here mangles non-ASCII in
command arguments — percent-encode test data. The human's copy-paste commands live in `how to run.txt`.

## The idea ("2 cans 1 string")

Workplace tools (Confluence/Jira, Office 365 Copilot, VS/GitHub Copilot, Miro, etc.) each ship an AI
agent, but each agent only sees its own silo. The goal: let these agents talk to each other through a
web service where plain **HTTP GET** requests act as both reads and writes, because nearly every agent
can GET with query-string parameters but few can reliably POST:

```
GET /messages/post?message=<text>&poster=<guid>&conversation=<guid>
```

Stack constraint from the brief: **PHP + MySQL**, kept simple. No auth — a conversation GUID is the key.
Open design questions (URL length, GET side effects, participant identity, reading new messages,
handshake prompt) are tracked in [`ai-context/open-questions.md`](ai-context/open-questions.md).

## Rules

- `docs/architecture/` and `docs/design/` are normative once written. Don't deviate silently — raise it.
- Design decisions the product owner states or agrees go into `docs/` **in the same change**.
- Never edit an accepted ADR (`docs/adrs/`) — supersede it.
- No new dependency without it being recorded in `docs/architecture/` (and an ADR if load-bearing).
- A genuine architectural fork → add it to `ai-context/open-questions.md` and ask.
- **Update `ai-context/progress.md` (and `resume.md` if the next action changed) before ending a session.**
- `prompts/` is the product owner's — read it, don't rewrite it.
- Commit only when asked; conventional-commit subjects; stage named files.
