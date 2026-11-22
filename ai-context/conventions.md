# Conventions

How to write code and docs here. The code sections are placeholders until the design is accepted;
the build session fills them in (PHP version, folder layout, DB access style, test approach) and
nothing here should be invented ahead of that design.

## Docs

- Normative docs (`docs/architecture/`, `docs/design/`) state rules: **what, then why, then the
  numbers.** A rule without its reason gets broken by the next person who doesn't see the point.
- Organised by topic, not by session. History and reasoning go in `ai-context/progress.md`'s
  session log and in ADRs.
- A design decision the product owner states or agrees is written down **in the same change** that
  implements it.
- Dates are absolute (`2026-11-22`), never "yesterday" or "last week".
- Link between docs with relative Markdown links.

## ADRs

- `docs/adrs/ADR-NNNN-kebab-title.md`, from [the template](../docs/adrs/TEMPLATE.md).
- Context → Decision → Consequences → Alternatives.
- Immutable once **Accepted**; to change one, write a new ADR and update only the old one's status
  line and the index.

## Code (PHP)

From the proposed design (ADR-0007, `docs/architecture/implementation.md`); the build session refines
this section as it learns.

- PHP 8.2+, `declare(strict_types=1);` in every file. Namespaces `Cans\…`, one class per file, PSR-4
  style paths under `src/` with a hand-written autoloader.
- **No dependencies**: no framework, Composer package, template engine, ORM or JS library without an
  ADR.
- **Database:** PDO only, prepared statements always, never string-built SQL with values. All times
  UTC (`UTC_TIMESTAMP()`, `gmdate`).
- **HTML output:** every dynamic value through one helper `e()` (`htmlspecialchars` with
  `ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8). No inline `<script>` (CSP).
- **Agent output:** every text comes from `Agent\Texts`, which mirrors `docs/design/agent-texts.md`;
  change both together. HTTP 200 + `ERROR:` for agent-facing errors.
- **Query parsing:** the message parameter only via `Http\LenientQuery`, never `$_GET`.
- **Keys:** only from `Domain\Keys`; never logged by our own code except within the conversation's
  activity rows.
- Small functions, early returns, no clever metaprogramming. Readable beats short.
- **Tests:** `php tests/run.php` must pass before a commit; every bug fix gets a regression test;
  `tests/smoke.ps1` before a merge.

## Git

- `main` is the default branch; feature branches `feature/<topic>` once there is code.
- Conventional-commit subjects: `feat(api): add post endpoint`, `docs(design): …`, `chore: …`.
- One concern per commit. Stage named files, never `git add -A` blindly.
- Never commit secrets: DB credentials live in a git-ignored local config file.
- Commit, push and open PRs only when the product owner asks.
