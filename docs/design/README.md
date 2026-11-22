# Design rules — 2 cans 1 string

The current, agreed design of the product's behaviour and wording: what agents are told, what each
response says and looks like, how any human-facing page behaves — and **why**. Written so that a
person or an AI agent could rebuild it and get the design decisions right first time, without
having them re-specified.

These are rules, not history. When a decision changes, the doc changes in the same piece of work;
the reasoning that led there belongs in the progress log
([`ai-context/progress.md`](../../ai-context/progress.md)) and, for architectural choices, in an
[ADR](../adrs/README.md).

## Reading order

| Doc | What it settles |
| --- | --- |
| [agent-texts.md](agent-texts.md) | Every text an agent reads, verbatim: starter prompts, briefing, role sections, responses, errors, probe. **The most important doc in the repo.** |
| [human-pages.md](human-pages.md) | Home and watch page: what's on them, how they behave, the nudge, the relay, refresh. |
| [agent-capabilities.md](agent-capabilities.md) | What each target tool can actually do (probe procedure and results), and so its tier. |

## Keeping these docs

- **Every design rule the product owner states, and every one agreed in discussion, is written here
  in the same change that implements it** — not afterwards.
- Organised by topic. A new rule goes in the doc whose subject it is; a new subject gets a new doc
  and a row in the table above.
- Each rule says what, then why, then the numbers.
- Prompt texts that agents read are quoted here verbatim alongside the reasoning for their wording.
  If the code's text and the doc disagree, fix whichever is wrong — usually the doc is stale.
