# Resume — read this first

You are working on **2 cans 1 string**: a tiny PHP/MySQL message board that lets AI agents in
different workplace tools (VS Copilot, Confluence/Rovo, M365 Copilot…) exchange messages, using only
HTTP GET (GET-as-write). The brief is [`prompts/the-idea.txt`](../prompts/the-idea.txt); the
orientation is [HANDOFF.md](HANDOFF.md).

## Branches

`feature/demo-polish` was merged into **`main`** on 2026-11-23. Work on `main` or a new feature
branch. The GitHub home will be https://github.com/matt-forth/2cans1string. No remote is configured
yet, and pushing waits for the product owner's go-ahead.

## Current state

**Live at https://2cans1string.com (deployed 2026-11-22, cPanel).** ADR-0001 to 0012 are
*Proposed*. Read [progress.md](progress.md): its newest session-log entries cover the deploy and the
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

1. **Decide Q15** (`open-questions.md`): should looping agents poll? It changes agent texts, so
   settle it before the demo. (Q16, Q17 answered; Q18 parked.)
2. **Push `main` to GitHub** (https://github.com/matt-forth/2cans1string; no remote configured yet),
   and redeploy the home page (GitHub link, new lede).
3. **Re-run the smoke test against the live site**, now that the redirect check is fixed:
   `tests\smoke.ps1 -Base https://2cans1string.com` (14/14 expected).
4. **Scan a QR code with a real phone**, and let term-a do the Chrome visual pass of the join-code
   screens (Chrome needed a restart).
5. **Probe cloud tools and trial with a non-Claude tool** (M365 Copilot, Rovo, VS Copilot;
   `how to run.txt` §4). Only Claude Code has used it so far, and R1 (agents refusing to build write
   URLs) is still the risk that matters. Record results in `docs/design/agent-capabilities.md`.

Updating the live site: `docs/deploy.md` §7. Keep the host's `config.local.php`; re-packaging wipes
`build/upload`.

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
