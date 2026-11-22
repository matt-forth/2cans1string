# Resume — read this first

You are working on **2 cans 1 string**: a tiny PHP/MySQL message board that lets AI agents in
different workplace tools (VS Copilot, Confluence/Rovo, M365 Copilot…) exchange messages, using only
HTTP GET (GET-as-write). The brief is [`prompts/the-idea.txt`](../prompts/the-idea.txt); the
orientation is [HANDOFF.md](HANDOFF.md).

## Branches

`main` has one commit, the baseline (`db7331f`). The demo-brief work is on **`feature/demo-polish`**
(12 commits, one per item), to be merged on the product owner's word. Pushing waits for their
go-ahead (no remote is configured yet).

## Current state

**Demo-ready locally; deployment kit ready, not deployed (2026-11-22).** ADR-0001 to 0010 are
*Proposed*. Read [progress.md](progress.md): its newest session-log entry explains the
terminal-to-terminal build over the board.

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

Waiting on the product owner to:

1. **Decide Q15–Q17** (`open-questions.md`): should looping agents poll; review on by default;
   default board titles. Q15 changes agent texts, so settle it before the demo.
2. **Merge `feature/demo-polish`** into `main`, then **push to GitHub** (no remote is configured yet).
3. **Choose the domain or sub-domain** for the site, with HTTPS: it becomes `base_url`, and every
   agent link carries it.
4. **Deploy** with [`docs/deploy.md`](../docs/deploy.md) (`dev\package.ps1`, then
   `tests\smoke.ps1 -Base https://…`), and probe cloud tools (`how to run.txt` §4). Record results in
   `docs/design/agent-capabilities.md`.
5. **Trial with a non-Claude tool** (M365 Copilot, Rovo, VS Copilot). Only Claude Code has used it so
   far, and R1 (agents refusing to build write URLs) is still the risk that matters.

If the deploy fails: the troubleshooting table in `docs/deploy.md` §5. The packaged layout passed the
smoke test locally, so a host failure is the host's configuration (PHP version, extensions,
`mod_rewrite`, a BOM in `config.local.php`, `base_url`).

## Working agreement

- Plan complex work first; record the plan in `plans/`.
- Small, reviewable commits, one concern each; commit only when asked.
- Every bug fix gets a regression test.
- Update [progress.md](progress.md) before ending a session, and `how to run.txt` whenever the way
  a human drives the tool changes.
- A genuine architectural fork → [open-questions.md](open-questions.md) and ask.
