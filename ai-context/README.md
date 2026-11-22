# ai-context

Working memory for AI coding agents on **2 cans 1 string**. Humans are welcome, but the audience is
agents.

| File | Purpose | Who updates it |
| --- | --- | --- |
| [HANDOFF.md](HANDOFF.md) | **Start here if you are new to this repo.** Self-contained brief: what the product is, current state, what's next, how to work here. | Humans, at session boundaries |
| [resume.md](resume.md) | How to pick up work cold, once oriented. Current state and next action. | Agents, every session |
| [architecture-summary.md](architecture-summary.md) | Compressed architecture — enough to write correct code without reading all of `docs/`. | Agents, on architectural change |
| [conventions.md](conventions.md) | Coding standards, and things that will get a change rejected. | Humans + agents |
| [glossary.md](glossary.md) | The ubiquitous language. Use these names in code, URLs and docs. | Humans + agents |
| [open-questions.md](open-questions.md) | Deliberately unresolved decisions and when to resolve them. | Humans + agents |
| [progress.md](progress.md) | Current state, what's done, what's next, known issues, session log. | **Agents, end of every session** |
| [plans/](plans/) | Per-phase / per-slice task breakdowns with acceptance criteria. | Agents when planning |

## Hard rules for agents

1. Read `resume.md` (and `architecture-summary.md`, once it has content) before writing code.
2. `docs/architecture/` and `docs/design/` are normative once written. If your instinct conflicts
   with them, the doc wins — or you raise it. You do not quietly deviate.
3. Never edit an accepted ADR. Supersede it with a new one (`docs/adrs/README.md`).
4. Never add a dependency (Composer package, JS library, service) that is not recorded in
   `docs/architecture/` without an ADR.
5. Update `progress.md` before you finish, and `resume.md` if the next action changed.
6. If a decision genuinely needs a human, add it to `open-questions.md` and stop — do not guess on
   architecture.
