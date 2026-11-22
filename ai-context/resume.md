# Resume — read this first

You are working on **2 cans 1 string**: a tiny PHP/MySQL message board that lets AI agents in
different workplace tools (VS Copilot, Confluence/Rovo, M365 Copilot…) exchange messages, using only
HTTP GET (GET-as-write). The brief is [`prompts/the-idea.txt`](../prompts/the-idea.txt); the
orientation is [HANDOFF.md](HANDOFF.md).

## Branches

`main` only so far. From the build onwards, one branch per slice (`feature/slice-0-probe`, …),
merged on the product owner's word. Pushing and pull requests wait for their go-ahead (no remote is
configured yet).

## Current state

**v1 design written (2026-11-22), awaiting the product owner's review. No code.** ADR-0001 to 0008
are *Proposed*. Read [progress.md](progress.md) for what was decided and why.

## What to read, in order

1. This file, then [progress.md](progress.md).
2. [architecture-summary.md](architecture-summary.md) — the design in ten minutes, and the rules that
   must not be violated.
3. [`docs/architecture/risks.md`](../docs/architecture/risks.md) — why the design looks the way it does.
4. [conventions.md](conventions.md).
5. The doc for your task (the table at the bottom of architecture-summary.md). For anything an agent
   reads: [`docs/design/agent-texts.md`](../docs/design/agent-texts.md).

## Build and test

```powershell
.\dev\php.ps1 tests\run.php      # unit tests
.\dev\serve.ps1                  # local site on http://localhost:9099
```

`dev\php.ps1` enables `pdo_mysql`/`mbstring` per run (the machine's PHP 8.5 has no php.ini). Local
MySQL 8.0 on 3306, database/user `cans` (see CLAUDE.md "Commands"). The human's versions of these
commands, and deployment steps, are in `how to run.txt`.

## Next action

**Slices 0, 1 and 2 are built and tested locally (2026-11-22)** — the whole v1 site works at
http://localhost:9099. Waiting on the product owner to:

1. **Trial it** with two or more AI chats, starting from the home page (`how to run.txt` §5). Record
   what the agents got wrong in `progress.md`; fix the texts in `docs/design/agent-texts.md` and
   `src/Agent/Texts.php` together.
2. Deploy to their hosting provider (`how to run.txt` §3) and probe cloud tools (§4); record in
   `docs/design/agent-capabilities.md`.

Then the rest of slice 3 (`plans/build-v1.md`): deployment notes, a three-agent trial, accept the ADRs.

If the deploy fails, help diagnose (PHP version, `pdo_mysql`, `.htaccess`, base_url with a
sub-folder path). The probe is fully verified locally against MySQL, so a host failure is the host's
configuration, not the code.

## Working agreement

- Plan complex work first; record the plan in `plans/`.
- Small, reviewable commits, one concern each; commit only when asked.
- Every bug fix gets a regression test.
- Update [progress.md](progress.md) before ending a session, and `how to run.txt` whenever the way
  a human drives the tool changes.
- A genuine architectural fork → [open-questions.md](open-questions.md) and ask.
