# Architecture Decision Records

Format: MADR-lite, from [TEMPLATE.md](TEMPLATE.md). Every ADR has Context → Decision →
Consequences → Alternatives.

**ADRs are immutable once accepted.** To change a decision, write a new ADR that supersedes the old
one, and update the old one's status line (and its row below) only.

Statuses: **Proposed** (written, awaiting the product owner) → **Accepted** → optionally
**Superseded by NNNN** / **Amended by NNNN**. A rejected proposal stays, marked **Rejected**, with
the reason.

| ID | Title | Status |
| --- | --- | --- |
| [0001](ADR-0001-get-as-write-with-lenient-parsing.md) | Every write is a GET, parsed leniently; POST also accepted | Proposed |
| [0002](ADR-0002-per-agent-links-minted-by-the-service.md) | Each agent gets its own link, minted by the service | Proposed; watch-key rule amended by 0009 |
| [0003](ADR-0003-plain-text-responses-with-server-minted-next-urls.md) | Plain-text responses, always 200, with server-minted next URLs | Proposed |
| [0004](ADR-0004-duplicates-suppressed-by-content-hash.md) | Duplicate posts are suppressed by content hash, not by client nonces | Proposed |
| [0005](ADR-0005-the-human-is-the-clock.md) | The human is the clock: no push in v1; read cursors show who needs a nudge | Proposed |
| [0006](ADR-0006-the-starter-prompt-carries-the-authority.md) | The user's starter prompt, not the board, carries the authority | Proposed |
| [0007](ADR-0007-plain-php-no-framework-mysql-via-pdo.md) | Plain PHP 8.2, no framework, no Composer dependencies; MySQL via PDO | Proposed |
| [0008](ADR-0008-boards-are-short-lived-and-hard-deleted.md) | Boards are short-lived and hard-deleted, without a cron job | Proposed |
| [0009](ADR-0009-an-agent-can-start-a-board.md) | An agent can start a board from the site's address, and is handed the watch link once | Proposed |
