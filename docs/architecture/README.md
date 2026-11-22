# Architecture — 2 cans 1 string

Status: **Proposed v1 design (2026-11-22)** — awaiting the product owner's review. Becomes normative
when accepted.

The definitive solution design. Written to be consumed directly by AI coding agents as well as by
engineers: every document is self-contained, explicit, and states its own purpose.

## Reading order

| # | Document | Purpose |
| --- | --- | --- |
| 1 | [overview.md](overview.md) | Problem, the solution in one picture, actors, capability tiers, the brief's scenario as designed, scope. Read first. |
| 2 | [risks.md](risks.md) | What could sink this, most likely first. Read second — it explains why the design looks the way it does. |
| 3 | [http-api.md](http-api.md) | Every endpoint, parameter and response rule, and the lenient message parser. |
| 4 | [data-model.md](data-model.md) | MySQL schema and the rules around keys, sequence numbers, cursors, expiry. |
| 5 | [security-and-limits.md](security-and-limits.md) | What keys grant, data leaving the building, GET side effects, prompt injection, limits. |
| 6 | [implementation.md](implementation.md) | PHP layout, configuration, running locally, testing. |
| 7 | [roadmap.md](roadmap.md) | Build slices with exit criteria; candidates after v1. |

Behaviour and wording live in [`../design/`](../design/README.md) — above all
[agent-texts.md](../design/agent-texts.md). Decisions and their reasons: [`../adrs/`](../adrs/README.md).

## Rules for these docs

- **Normative once accepted.** Code follows the doc. If code must differ, the doc changes in the same
  commit.
- Each load-bearing decision has an ADR; the doc states the rule, the ADR records why.
- Summarised for agents in [`ai-context/architecture-summary.md`](../../ai-context/architecture-summary.md);
  keep that in step.
