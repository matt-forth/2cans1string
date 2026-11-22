# HANDOFF — 2 cans 1 string

> Give this file to a coding agent (Claude Code, GitHub Copilot or similar) to continue the work.
> It is self-contained: read this first, then the documents it points at.
> Last updated: 2026-11-23 (sections 2–3 brought up to date; the rest dates from the design session).
>
> If this file and [resume.md](resume.md) / [progress.md](progress.md) disagree on the current
> state, those two win — they are updated every session; this one only at milestones.

---

## 1. What this project is

**2 cans 1 string** (working name; the brief also calls it "ai-link") is a deliberately simple web
service that lets AI agents living in different workplace tools talk to each other.

Each tool's agent — Confluence/Jira AI, Office 365 Copilot, Visual Studio / GitHub Copilot, Miro,
etc. — only sees its own silo of context. Proper integrations (MCP servers, connectors) are hard to
get approved in a corporate setting. But nearly every agent can make a plain **HTTP GET** with
query-string parameters. So the service makes GET requests act as both reads and writes:

```
GET /messages/post?message=<text>&poster=<guid>&conversation=<guid>
```

A human starts a **conversation**, hands its URL to two or more agents, and they exchange messages,
instructions and results through it — one agent typically acting as the parent that delegates a
task (e.g. "research the documented requirements for feature X") to another acting as a child.

The original problem statement, in the product owner's words: [`prompts/the-idea.txt`](../prompts/the-idea.txt).
It is the brief. Read it in full before any design work.

Constraints already stated in the brief:

- **Stack: PHP server-side, MySQL if/where a database is needed.** Keep it simple.
- **No authentication.** Knowing a conversation's GUID is sufficient. Conversations are short-lived.
- **More than two agents per conversation** must be supported.
- Every write must be expressible as a GET (URL length limits, encoding, and the caching/prefetch
  side effects of GET all matter).
- Responses must be easy for an LLM to read back via a simple fetch (plain text or simple JSON),
  and must carry enough prompting for agents to "handshake" and understand their roles.

## 2. Current state

**Built and live at https://2cans1string.com (deployed 2026-11-22, cPanel shared hosting).**
Slices 0–2 plus the demo brief (pause and review, conversation view, QR codes, join codes, deploy kit)
are on `feature/demo-polish`. The design is in `docs/architecture/`, `docs/design/` and `docs/adrs/`
(ADR-0001 to 0012, all still Proposed); compressed in
[architecture-summary.md](architecture-summary.md). See [progress.md](progress.md) for what was
decided and why, including where the design departs from the brief's sketch.

## 3. What's next

The live list is the "Next action" section of [resume.md](resume.md). In short: merge
`feature/demo-polish` and push to GitHub, then trial non-Claude tools (M365 Copilot, Rovo,
VS Copilot). So far only Claude Code has used the service, and the biggest risk is still that other
agents won't make these requests ([`docs/architecture/risks.md`](../docs/architecture/risks.md)).

## 4. Repository layout

| Path | What it holds |
| --- | --- |
| `prompts/` | The product owner's own prompts and the brief. Theirs — read, don't rewrite. |
| `ai-context/` | Agent working memory (this folder). |
| `docs/architecture/` | The normative design. |
| `docs/design/` | Agreed rules for behaviour and wording, with reasons. |
| `docs/adrs/` | Architecture Decision Records. Immutable once accepted. |
| `public/`, `src/`, `db/`, `tests/`, `dev/` | The code: web root, PHP sources, schema, tests, dev scripts. |
| `how to run.txt` | Copy-paste commands for the human (tests, local run, deploy, probes). |
| `CLAUDE.md`, `.github/copilot-instructions.md` | Pointers into `ai-context/` for each agent tool. |

## 5. Working agreement for the agent

1. Read `resume.md`, `progress.md`, and the brief before starting.
2. Plan complex work first and record the plan under `ai-context/plans/`.
3. Design decisions the product owner states or agrees go into `docs/` **in the same change**.
4. Never edit an accepted ADR — supersede it.
5. Small, reviewable commits; conventional-commit subjects (`docs(design): add message model`).
   Commit only when the product owner asks.
6. **Update `ai-context/progress.md` before ending a session.**
7. A genuine architectural fork → add it to `open-questions.md` and **ask**.

## 6. About the product owner

Senior solutions architect / developer (.NET, C#, web APIs, Vue, TypeScript, developer tooling,
CI/CD, AWS). **Do not explain basics.** Be direct. Push back when you see a problem with an idea or
a better approach — that's expected and wanted. Don't make things up; if something isn't
established, say so or ask. Runs PowerShell on Windows: any command handed over is one complete,
copy-pasteable line with real absolute paths.

## 7. Suggested opening message to the agent

> Read `ai-context/HANDOFF.md`, then `ai-context/resume.md`, `ai-context/progress.md` and
> `ai-context/architecture-summary.md`. Build slice 0 (the probe) from `ai-context/plans/build-v1.md`,
> then stop so we can run it against my tools.
