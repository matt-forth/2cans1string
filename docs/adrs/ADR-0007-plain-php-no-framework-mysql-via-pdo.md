# ADR-0007 — Plain PHP 8.2, no framework, no Composer dependencies; MySQL via PDO

- Status: Proposed
- Date: 2026-11-22
- Related: [implementation.md](../architecture/implementation.md)

## Context

The brief fixes PHP and MySQL and asks to keep it simple. The application is ~15 routes, 5 tables,
and text templates. It should deploy to the cheapest shared PHP host or an internal server by copying
files.

## Decision

- PHP 8.2+, `declare(strict_types=1)`, one front controller with a small regex router, PDO
  (`ERRMODE_EXCEPTION`, real prepared statements), PHP files as templates with `htmlspecialchars`.
- No framework, no Composer packages, no template engine, no ORM. A tiny assert-based test runner
  instead of PHPUnit.
- MySQL 8.0+ or MariaDB 10.6+, `utf8mb4_unicode_ci` (portable across both).
- Adding any dependency requires a new ADR stating why the plain version failed.

## Consequences

### Positive
- Deploys anywhere PHP runs; nothing to install or update; the whole codebase is readable in one
  sitting.

### Negative
- Routing, escaping and request parsing are hand-written; correctness rests on tests of those few
  pieces (the lenient parser above all).

## Alternatives considered

- **Slim / Laravel** — routing and structure for free, at the cost of Composer, a vendor directory
  and upgrade churn, for an app with fifteen routes.
- **SQLite instead of MySQL** — zero setup and arguably a better fit for a trial; not chosen because
  the brief asks for MySQL and the product owner's host only offers MySQL.
- **SQLite locally, MySQL on the host** — two dialects (ENUM, `LAST_INSERT_ID`, `UTC_TIMESTAMP`)
  means local tests wouldn't prove what runs live. MySQL everywhere (Q12, 2026-11-22).
