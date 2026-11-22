# Copilot instructions — 2 cans 1 string

This repository keeps its agent guidance in `ai-context/`. Read these before doing anything:

1. **`ai-context/HANDOFF.md`** — what the product is, current state, how to work here. Start here.
2. **`ai-context/resume.md`** — current state and the next action.
3. **`ai-context/progress.md`** — what's done; session log, newest first.
4. **`prompts/the-idea.txt`** — the original brief.

`docs/architecture/` and `docs/design/` are **normative** once written, and `docs/adrs/` records why
each decision was made. If your instinct conflicts with a document, the document wins — or you raise
it. Do not deviate silently.

## Working agreement

- Never edit an accepted ADR — supersede it with a new one.
- Record design decisions in `docs/` in the same change that makes them.
- Update `ai-context/progress.md` before ending a session.
- Genuine architectural forks go in `ai-context/open-questions.md`, and you ask.
- Conventional-commit subjects. One concern per commit.
