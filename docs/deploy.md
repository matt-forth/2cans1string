# Deploying to shared PHP hosting

How to put 2 cans 1 string on an ordinary PHP + MySQL host (cPanel, Plesk, DirectAdmin and the
like), from an empty account to a passing smoke test, in about half an hour. *Written 2026-11-22
(demo brief C).* The copy-paste commands for this machine are in `how to run.txt` §3. This page
explains them.

## 1. What the host needs

- **PHP: pick the newest version your host offers**, with `pdo_mysql` and `mbstring`. Most panels
  have a PHP version selector. The code targets 8.2+ and uses nothing newer, but it has only been
  *tested* on 8.5. 8.2 leaves security support at the end of 2026.
- **MySQL 8.0+ or MariaDB 10.6+**, one database and one user.
- **Apache with `.htaccess`** (nearly all shared hosts), with `mod_rewrite`.
- **HTTPS** on the domain or sub-domain. Most panels issue a free Let's Encrypt certificate.
  Cloud AI tools refuse plain-HTTP links, and the site redirects HTTP to HTTPS (§4).
- **No cron needed.** Expired boards are deleted as requests arrive (§6).

## 2. Database

1. In the panel, create a database and a user with all privileges on it. Note the host name (often
   `localhost`), the database name, the user and the password.
2. Import `db/schema.sql` (phpMyAdmin → Import, or the panel's SQL tool). It creates six tables.
3. Upgrading a database created from an older `schema.sql`? Instead of step 2, run each file in
   `db/upgrades/` that's newer than the database, oldest first.

## 3. Build the upload folder

```powershell
powershell -ExecutionPolicy Bypass -File C:\MATT\VS\2cans1string\dev\package.ps1
```

This makes `build\upload\`, laid out like your hosting home folder. Pick the layout your host allows:

| Layout | When | Result |
| --- | --- | --- |
| **above** (default, preferred) | You can upload *beside* the web root (most hosts: your home folder holds `public_html/`) | `public_html/` (the web root) gets `index.php`, `.htaccess`, `.user.ini`, `robots.txt`, `assets/`. Beside it go `src/`, `bin/`, `config.default.php` and your `config.local.php`. The web can't reach the code at all. |
| **inside** (`-Layout inside`) | The host only lets you upload *into* the web root | The code goes in `public_html/app/`, locked by `app/.htaccess` (copied from `deploy/app.htaccess`: deny all, for Apache 2.2 and 2.4). |

Is your web root called `www` or `htdocs`? Add `-WebRoot www`. `public/index.php` finds the code in
either layout by itself.

**The web root isn't directly in your home folder?** cPanel addon domains are the usual case: the web
root is something like `~/2cans1string.com/www`. Build with `-WebRoot www`, then upload the contents
of `build\upload\` into the folder **above** the web root (`~/2cans1string.com/`), not into your
home folder. The code then sits beside `www/`, outside it. (First real deploy, 2026-11-22.)

**Keep what the host already put in the web root.** Folders such as `.well-known/` (certificate
checks) and `cgi-bin/` belong to the host. Upload around them; don't delete or replace them.

**Re-running `package.ps1` wipes `build\upload\`**, including a `config.local.php` you put there.
Keep the host's config somewhere else too.

## 4. Configure

Create `config.local.php` from `config.local.php.example`, next to `config.default.php` (in
`build\upload\` for layout *above*, in `build\upload\public_html\app\` for *inside*):

```php
<?php

declare(strict_types=1);

return [
    'base_url' => 'https://cans.example.com',     // the public https address, no trailing slash;
                                                  // a sub-folder works: https://example.com/cans
    'secret' => '…a long random string…',
    'probe_key' => '…another long random string…', // keep a copy: it opens /probe/log
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=…;charset=utf8mb4',
        'user' => '…',
        'password' => '…',
    ],
];
```

- **Save it as UTF-8 *without* a BOM.** A BOM before `<?php` breaks `declare(strict_types=1)` and
  every page returns 500. This happened in the packaging rehearsal. Notepad and VS Code default to
  no BOM, but PowerShell 5.1's `Set-Content -Encoding utf8` adds one.
- Two random strings: `[Convert]::ToBase64String((1..32 | % { [byte](Get-Random -Max 256) }))`.
- The file is git-ignored and is **never** packaged from your machine (yours points at localhost).

What production gets from the shipped files, with nothing to set:

- **HTTPS redirect** (`public/.htaccess`): any `http://` request is redirected 301 to `https://`.
  It skips requests already on HTTPS behind a proxy (`X-Forwarded-Proto`), to avoid loops, and
  skips `localhost`. No certificate yet? Comment out those four lines until there is one.
- **Errors are logged, never shown.** `public/.user.ini` turns `display_errors` off before PHP starts
  (FastCGI/FPM hosts). `src/bootstrap.php` does the same at run time. Uncaught errors are logged with
  `error_log` and the client gets a one-line message.
- **Dotfiles aren't served** (`.htaccess`, `.user.ini`), and no directory listings. The one
  exception is `/.well-known/`: Let's Encrypt and cPanel AutoSSL renew the certificate by reading
  `/.well-known/acme-challenge/…` over plain HTTP. That path is exempt from both the dotfile block
  and the HTTPS redirect. Without the exemption, renewals fail silently and HTTPS lapses about 90
  days later. (The first real deploy, 2026-11-22, caught this before it bit. A unit test checks the
  rule order, and the smoke test checks the path isn't 403.)

## 5. Upload and smoke test

1. Upload the *contents* of `build\upload\` into your hosting home folder with the panel's file
   manager or FTP. Keep the dotfiles: some FTP clients hide them.
2. Run the smoke test from this machine:

   ```powershell
   powershell -ExecutionPolicy Bypass -File C:\MATT\VS\2cans1string\tests\smoke.ps1 -Base https://cans.example.com
   ```

   It starts a throwaway board as an AI would, posts accented text and reads it back as the helper,
   checks the board page, checks HTTP→HTTPS, checks that `config.local.php`, code and dotfiles
   aren't served, then deletes the board. Every line should say `pass`.
3. Open the home page in a browser, start a board, and paste the Step 1 message into a real AI
   assistant.

**If it fails:**

| Symptom | Likely cause |
| --- | --- |
| 500 or a blank page everywhere | PHP older than 8.2, `pdo_mysql`/`mbstring` off, or a BOM in `config.local.php`. Check the panel's error log. |
| "Something went wrong on the server" | Database settings wrong, or `schema.sql` not imported. The error log says which. |
| 404 for everything but the home page | `mod_rewrite` off, or `.htaccess` didn't upload. |
| Redirect loop | TLS ends at a proxy that doesn't send `X-Forwarded-Proto`. Comment out the HTTPS lines and use the panel's own "force HTTPS" switch. |
| Links in agent responses point at the wrong address | `base_url` in `config.local.php`. |

## 6. Expired boards (no cron)

Boards are hard-deleted an hour after they expire, with everything in them
([ADR-0008](adrs/ADR-0008-boards-are-short-lived-and-hard-deleted.md)). The site does this itself:
on every new board and on about 2% of all requests. On a quiet site an expired board can therefore
linger for a while, but agents and the board page already treat it as gone.

**Optional cron**, if boards must vanish promptly even when nobody visits:

```text
17 * * * *  php /home/you/bin/purge.php        (layout above)
17 * * * *  php /home/you/public_html/app/bin/purge.php   (layout inside)
```

`bin/purge.php` runs only from the command line (a web request gets a 404). It deletes expired boards
and probe-log entries older than 7 days, and prints what it deleted.

## 7. Updating a live site

Re-run `dev\package.ps1`, upload over the old files, and keep the host's `config.local.php`. If the
change added a file in `db/upgrades/`, run it once first. Then run the smoke test again.
