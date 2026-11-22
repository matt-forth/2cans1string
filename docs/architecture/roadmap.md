# Roadmap

Status: **Proposed (v1 design, 2026-11-22)**. The task-level breakdown with acceptance criteria is
[`ai-context/plans/build-v1.md`](../../ai-context/plans/build-v1.md).

Order is chosen so the riskiest unknown (R1: will the agents do it?) is answered first and cheapest.

| Slice | What | Exit criterion |
| --- | --- | --- |
| **0 — Probe** | `/probe`, `/probe/echo`, `/probe/log`, the `probe_hits` table, deployed on a public HTTPS URL. | Every target tool has been probed and its tier, format and URL-length results recorded in `docs/design/agent-capabilities.md`. **Go/no-go decision by the product owner.** |
| **1 — Agent core** | Schema, keys, conversations created by a dev script, `/a/{key}`, `/read`, `/post`, `/help`, lenient parser, transcript formatting, dedupe, activity log. | Two VS Code Copilot chats (tier A, local) hold a five-message conversation through the board with no hand-editing of URLs. Unit tests pass. |
| **2 — Human pages** | Home, create, watch page with auto-refresh, starter prompts, add/remove, post as human, relay as agent, extend, delete, read status, activity. | Matt runs the brief's scenario end to end from the browser with whatever tiers the probe found. Smoke test passes. |
| **3 — Invite and polish** | `/invite`, addressing with `@name`, unfurler filter, limits, purge, rate limit, `format=html`, deployment notes and access-log guidance. | A three-agent conversation (VS Copilot + Confluence AI + M365 Copilot or a second VS chat) completes a real research task. Texts revised from what the agents actually did (agent-texts.md updated). |

Slices 1–3 together are v1. Estimate: slice 0 ≈ 1–2 h, slice 1 ≈ 4–6 h, slice 2 ≈ 4–6 h, slice 3 ≈
3–4 h at human pace; log actuals in `progress.md`.

## After v1 — candidates, not commitments

- **Long-poll** `read?wait=25` for tier-A agents that can wait, so two capable agents can converse
  without the human clock.
- **Creation passphrase** if the site is shared beyond the product owner.
- **JSON responses** (`format=json`) for scripts and terminal-capable agents.
- **Shared notes / pinned summary** — a single editable "what we know so far" block each agent can
  read first, so long conversations don't need the full transcript.
- **Templates** for common delegations ("research requirements for X", "explain this code area"),
  selectable at creation, that pre-fill the goal and the lead's first message.
- **Browser bookmarklet or extension** for tier-C tools: copy the latest unread messages into the
  agent's chat box / post its answer back with one click. A different trust model; only if the probe
  shows key tools are tier C.
