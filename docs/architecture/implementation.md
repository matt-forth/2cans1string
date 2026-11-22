# Implementation

Status: **Proposed (v1 design, 2026-11-22)**.

Plain PHP, no framework, no Composer dependencies, one front controller
([ADR-0007](../adrs/ADR-0007-plain-php-no-framework-mysql-via-pdo.md)). The whole thing should be a
few hundred lines of PHP plus templates — small enough to read in one sitting and to deploy by
copying files to any PHP host.

## Platform

| Thing | Version / choice | Why |
| --- | --- | --- |
| PHP | 8.2+ (`pdo_mysql`, `mbstring`) | Current supported line; available on any host. |
| Database | MySQL 8.0+ or MariaDB 10.6+, **locally and on the host** | The host only offers MySQL; one dialect everywhere means local tests prove what runs live. Collation `utf8mb4_unicode_ci` works on both. |
| Hosting | The product owner's PHP/MySQL hosting provider | Public HTTPS URL that cloud-hosted agents can reach. |
| Web server | Apache with `.htaccess` (shared hosting) or nginx; PHP's built-in server for local dev | Front controller works on all three. |
| JS | one vanilla file, no build step | Auto-refresh and copy buttons on the watch page only. |
| JS dependency | **qrcode-generator 1.4.4** (MIT), vendored in `public/assets/vendor/qrcode-generator/` with its licence and npm integrity hash | Draws the "Show QR code" panel in the browser ([ADR-0011](../adrs/ADR-0011-qr-codes-open-a-join-page-drawn-by-a-vendored-library.md)). The only third-party code. Unchanged from upstream; re-vendor deliberately to update. |
| CSS | one small hand-written file | Readable, not pretty. |

## Layout

```text
public/                     ← web root; nothing else is web-reachable
  index.php                 ← front controller: bootstrap, route, dispatch
  .htaccess                 ← rewrite everything that isn't a file to index.php
  assets/app.css
  assets/watch.js
  robots.txt
src/
  bootstrap.php             ← config, error handling, PDO, autoload (simple spl_autoload_register)
  Config.php                ← loads config.local.php over config.default.php
  Db.php                    ← PDO factory (utf8mb4, ERRMODE_EXCEPTION, UTC session, emulate prepares off)
  Http/
    Router.php              ← ordered [method, regex, handler] table
    LenientQuery.php        ← the message parser (http-api.md §4) — the trickiest code here
    Responses.php           ← text(), html(), json(), redirect(), common headers
  Domain/
    Keys.php                ← key generation and validation
    Conversations.php       ← create, find by watch key, extend, delete, purge
    Participants.php        ← add/invite, find by agent key, mark read/seen, remove
    Messages.php            ← post (seq, dedupe, mentions), list after, page
    Activity.php            ← log, trim
    RateLimit.php           ← creates per IP hash per hour
  Agent/
    AgentController.php     ← /a/{key}, /read, /post, /invite, /help
    Texts.php               ← every agent-facing text, as templates (agent-texts.md is the spec)
    TranscriptFormatter.php ← messages → the plain-text format (http-api.md §6), pagination
  Web/
    WebController.php       ← /, /new, /w/{key}…
    templates/*.php         ← home, watch, expired, layout (htmlspecialchars everywhere)
  Probe/
    ProbeController.php
config.default.php          ← committed defaults
config.local.php            ← git-ignored: db credentials, base_url, secret, probe key
db/
  schema.sql                ← data-model.md, verbatim
tests/
  run.php                   ← plain-PHP test runner, no framework
  unit/*.php                ← LenientQuery, Keys, TranscriptFormatter, mentions
  smoke.ps1                 ← end-to-end against a running site, via Invoke-WebRequest
dev/
  php.ps1                   ← runs PHP with pdo_mysql + mbstring enabled, without touching php.ini
  serve.ps1                 ← built-in server on localhost:9099
```

`src/App.php` builds the router and holds the route table; `src/Http/Request.php` wraps the
superglobals (path relative to `base_url`'s path, lower-cased; raw query untouched).

**Deployment layouts.** `public/index.php` looks for the app beside `public/` (layout 1: code outside
the web root) and otherwise in `public/app/` (layout 2: hosts that only allow uploads into the web
root, with `app/.htaccess` from `deploy/app.htaccess`). The full guide is
[docs/deploy.md](../deploy.md).

*Added 2026-11-22 (demo brief C):* `public/.htaccess` redirects HTTP→HTTPS (proxy-aware, skips
localhost) and refuses dotfiles. `public/.user.ini` turns error display off for FastCGI/FPM hosts.
`deploy/app.htaccess` is the layout-2 lock. `bin/purge.php` is the optional cron (CLI only).
`dev/package.ps1` builds `build/upload/` for either layout. `tests/smoke.ps1 -Base <url>` is the
end-to-end check against a running site. The dev front controller also refuses dotfiles, so local
behaves like Apache.

Namespaces: `Cans\Http`, `Cans\Domain`, `Cans\Agent`, `Cans\Web`, `Cans\Probe`. `declare(strict_types=1);`
in every file.

## Configuration

`config.default.php` returns an array; `config.local.php` (git-ignored) returns overrides.

| Key | Default | Meaning |
| --- | --- | --- |
| `base_url` | *(required)* | Absolute public URL, no trailing slash. Used for every URL the service prints. |
| `db.dsn`, `db.user`, `db.password` | *(required)* | PDO connection. |
| `secret` | *(required)* | Random string; salts IP hashes. |
| `probe_key` | *(required)* | Guards `/probe/log`. |
| `human_name` | `human` | Name of the human participant in new conversations. |
| `max_message_chars` | 8000 | After decoding. GET messages are usually limited well below this by URL length. |
| `max_participants` | 12 | Per conversation, including the human. |
| `max_messages` | 500 | Per conversation. |
| `max_creates_per_ip_hour` | 30 | Conversation creation rate limit. |
| `dedupe_minutes` | 10 | Duplicate suppression window. |
| `page_messages`, `page_chars` | 20, 12000 | Agent response pagination. |
| `expiry_choices_hours` | `[1, 24, 168]` | Offered on the home page; default the middle one. |

## Request flow

`index.php` → `bootstrap.php` (config, PDO lazily) → purge check (2%) → `Router` matches →
controller → `Responses`. Any uncaught exception: logged with `error_log`, and the client gets 500
with a one-line text body (agents) or a plain error page (humans). No stack traces to clients.

Every agent request writes one `activity` row (in `AgentController`, in a `finally`), whatever
happened.

## Running it locally

PHP's built-in server with the front controller as router:

```powershell
powershell -ExecutionPolicy Bypass -File C:\MATT\VS\2cans1string\dev\serve.ps1
```

Useful immediately for VS Code Copilot, whose fetch runs on the local machine. **Cloud-hosted
agents (Rovo, M365 Copilot) fetch from the vendor's servers, so they cannot reach `localhost`** — for
them the site runs on the product owner's hosting provider (public HTTPS). Deployment is copying
`public/`, `src/`, `db/` and `config.local.php` up and loading `db/schema.sql` once. See [security-and-limits.md](security-and-limits.md)
on what a public URL implies. The build session writes the exact commands into `how to run.txt`.

## Testing

- **Unit (`tests/run.php`)**: a tiny assert-based runner, no PHPUnit. Must cover the lenient parser
  (unencoded `&`, `+` vs `%20`, double encoding, invalid UTF-8, stray trailing `&r=`, aliases,
  empty), mention extraction, key alphabet, transcript formatting and pagination boundaries.
- **Smoke (`tests/smoke.ps1`)**: against a running instance with a scratch database: create a
  conversation via the form post, fetch the agent link, invite, post from both agents, read, check
  duplicate suppression, check expiry handling, delete. Run it before every merge.
- The probe and the first trial with real agents are the acceptance test that matters
  ([roadmap.md](roadmap.md)).

## Deliberately absent

No ORM, no template engine, no DI container, no Composer, no front-end framework, no queue, no
cron, no sessions or cookies. Add one only with an ADR and a reason the simple version failed.
