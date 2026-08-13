# ModularTemplate

A bare-bones, dependency-free starting point for a self-hosted web
application: plain PHP 8.1+ and MySQL/MariaDB, no Composer, no build step, no
framework. Clone it, install it, and build your application onto a foundation
that already handles the unglamorous parts:

- **Accounts** - sign-up, email verification, login (rate-limited by IP and
  by account), password change and emailed reset, email change with a
  "wasn't you?" revert link to the old address, and sessions that die with a
  password change.
- **Install & upgrade** - a web setup wizard and an equivalent CLI installer
  (`php bin/install.php`) that provision the database, a least-privilege
  runtime account, and the schema, then write `.env`. Schema changes ship in
  `schema.sql` and are applied by the same installer (missing tables, column
  drift, guarded index migrations) - never by hand.
- **Admin settings** - outgoing mail (SMTP relay, from-address), the site's
  About/Terms/Privacy texts, and an in-browser test-suite runner. The first
  account created is the administrator.
- **A server-side render system** - every concept is a PHP class with a
  `toDOM()` method building real DOM (no string templating, no `innerHTML`
  anywhere), with the PHP class name doubling as the CSS and JS selector.
- **A client toolkit** - plain ES modules served as-is: one `Api.js` POST
  wrapper (CSRF header, error toasts), infinite scrolling, live search,
  dialogs, toasts, form field errors, theme switching.
- **i18n** - one JSON file per language under `locales/`, read by server and
  browser alike. English ships; add a language by adding a file.
- **Security defaults** - HTTPS required and proven live at install time
  (including a forged-Host-header spoof test on the redirect), centralized
  CSRF verification, prepared statements everywhere, security headers, `.env`
  denied by the web server and created `0600`.
- **Two test suites** - a reflection-based PHP runner (`php
  bin/run-tests.php`, no PHPUnit) and a jsdom-based JS runner (`npm test`),
  both green out of the box.

`CLAUDE.md` documents the architecture and conventions for
[Claude Code](https://claude.com/claude-code), so you can point it at a fresh
clone and start building features that fit the house style.

## Requirements

- PHP 8.1+ (web SAPI + CLI) with `mysqli`, `curl`, `dom`, `libxml`,
  `mbstring`
- MySQL or MariaDB
- Apache with `mod_rewrite` (the shipped `.htaccess` handles routing), or an
  equivalent nginx config of your own
- HTTPS - the site refuses to serve over plain HTTP. For a real domain use
  Let's Encrypt (`certbot`); for local development use
  [mkcert](https://github.com/FiloSottile/mkcert) or your distribution's
  self-signed default.

## Install

1. Clone into your web root and point an HTTPS vhost at it. Set
   `ServerName your.domain` and `UseCanonicalName On` (the installer proves
   this live - without it, the HTTPS redirect can be spoofed via a forged
   Host header).
2. Either visit the site in a browser (the setup wizard appears while no
   `.env` exists) or run `php bin/install.php` in a terminal. Both walk the
   same steps: environment checks, site URL/title, mail from-address,
   database provisioning with one-time admin credentials, `.env`.
3. Sign up - the first account becomes the administrator, and Admin Settings
   appears in its account menu. Configure the SMTP relay there so
   verification and reset emails deliver (PHP's `mail()` is the fallback and
   lands in spam on a typical VPS).

## Upgrading

Deploy new code, then run `php bin/install.php` again whenever a release
bumps `APP_VERSION` (the site shows a maintenance page until you do). The
installer creates missing tables, reconciles schema drift against
`schema.sql`, and stamps the new version. Set `DB_ADMIN_USERNAME` /
`DB_ADMIN_PASSWORD` in `.env` to let pending migrations apply themselves on
the first request after a deploy instead.

## Building your application

The short version (the long one is `CLAUDE.md`):

- A page is a root-level PHP file that requires `src/init.php` and builds a
  `Page`. A JSON endpoint lives under `api/` and requires
  `api/api-init.php`.
- A concept is a class in `src/classes/` extending `HTMLObject` (usually via
  a primitive like `Div`), hydrated straight off its table's columns, and
  rendered by `toDOM()`. Its PHP class name is its CSS selector and its JS
  twin's name.
- A self-loading list extends `ItemList`, owns its query in `rows()`, and
  gets paging for free via `InfiniteScroller.js`.
- Schema changes go in `schema.sql` plus an `APP_VERSION` bump.
- Tests go in `tests/` (PHP) and `tests/js/` (JS); both runners discover
  them by naming convention.

## License

MIT - see `LICENSE.txt`.
