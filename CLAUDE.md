# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with
code in this repository.

# ModularTemplate

A bare-bones starting point for a self-hosted web application. Plain PHP 8.1+
and MySQL/MariaDB (`mysqli`), **no Composer, no build step, no SPA
framework** - the server stack, down to the SMTP client and HTML renderer, is
hand-rolled, and the browser runs plain ES modules served as-is. The only
third-party code is Bootstrap CSS and the Inter webfont from a CDN, and
`jsdom` as a dev-only dependency for the JS tests.

What ships working out of the box: install (web wizard or CLI), accounts
(sign-up, email verification, login, password change/reset, email
change/revert, sessions that die with a password change), an admin settings
page (outgoing mail, site texts, test runner), i18n, themes, and both test
suites. Everything else is deliberately absent - build features onto this
rather than deleting features out of it.

## Commands

- **Run the PHP test suite:** `php bin/run-tests.php` - hand-rolled,
  reflection-based (no PHPUnit). A test is any `public function test*()` on a
  `tests/*Test.php` class extending `TestCase`. Exits non-zero on failure.
- **Run selected PHP tests:** `php bin/run-tests.php --only=ClassTest,OtherTest`.
  Use `--no-db` to force a filesystem/unit-only run even as root; `--help`
  documents the runner. The runner points ordinary tests at a deliberately
  nonexistent database so a test that forgot `DatabaseTestCase` cannot touch
  the configured application database.
- **Run DB-backed tests too:** `sudo php bin/run-tests.php` -
  `DatabaseTestCase` subclasses build and drop a throwaway database (needs
  the DB root account), so they're silently skipped when not run as root.
- **Run the JS test suite:** `npm test` (`node bin/run-js-tests.js`) - a
  hand-rolled jsdom-based runner. A `tests/js/*Test.js` file `export default`s
  an object `{ suite, tests }` where `tests` maps a name to a function that
  asserts via the `TestCase.assert*` statics (`tests/js/TestCase.js`). `jsdom`
  is the sole `node_modules` dependency.
- **Apply a schema change / prove the upgrade path:**
  `php bin/install.php`. The installer is the one authoritative migration
  path - never hand-run `ALTER`s. Add new DDL to `schema.sql` and bump
  `APP_VERSION` in `src/init.php` for any schema change or the upgrade won't
  fire; init.php locks the site to a maintenance page until code and database
  agree.
- **No build step and no linter** - `.js`/`.css` are served as-is and edited
  directly. Styles are six modular sheets under `styles/` (loaded in this
  cascade order: `themes`, `base`, `utilities`, `components`, `layout`,
  `mobile`) - there is no single `style.css`. Client code is PascalCase ES
  modules under `scripts/`, served and imported by absolute URL
  (`/scripts/Api.js`); `scripts/main.js` is the one `<script type="module">`
  entry point (see Client mirroring).

## Bootstrap

- **Page scripts** live at the project root (`login.php`, `about.php`, ...)
  and `require __DIR__ . '/src/init.php'` first. `init.php` registers the
  `src/classes/{ClassName}.php` autoloader, enforces HTTPS + canonical host,
  starts the session, connects the DB, and gates the whole site behind a
  maintenance page when `APP_VERSION` ≠ the DB's recorded version.
- **`init.php` exits under CLI** (no HTTPS/Host). To poke at classes from a
  scratch script, register the autoloader yourself, `require 'src/config.php'`
  and `require 'src/functions.php'` - the same things `bin/run-tests.php` does
  - instead of including `init.php`.
- **`src/functions.php` holds the standalone helpers** (`truncate()`,
  `safe_json_for_script()`, `current_url()`), loaded explicitly by every
  bootstrap since functions have no class name to autoload by. Look there
  before writing a helper, and move a reusable helper there on sight when one
  turns up buried in a class (refactoring its callers to match).
- **JSON endpoints** live under `api/` and include `api/api-init.php`. They
  return JSON only - the server never ships HTML fragments over AJAX.

## The render system (`HTMLObject`)

Every concept on the site is an `HTMLObject` subclass in `src/classes/`.

- **Hydration:** most objects are hydrated straight off a query -
  `mysqli_fetch_object` sets one property per column *before* the constructor
  runs (`#[\AllowDynamicProperties]` lets incidental columns ride along). So an
  object's properties mirror its table's columns one-to-one, by name.
- **`toDOM()`** builds a `\DOMElement` into the shared `HTMLObject::$document`;
  output is **one-shot** (`markRendered()` throws on a second call) because
  children are built into `$contents` and queries run during render. Build a
  fresh instance to render again. `toJSON()` is the AJAX counterpart.
- **`deriveClassName()`** sets the CSS class to the whole PHP-class chain from
  the ancestor that first declared `$class` down to the concrete class. **The
  PHP class, CSS selector, and JS selector are deliberately the same string.**
  Generic primitives (`Div`, `Button`, `Anchor`) never declare `$class`, so
  they get no class name.

## List / Section pattern

- A `<ul>` that loads rows is a **List** (`class` ends in `List`); a
  `<section>` wrapping one with a heading is a **Section** (ends in
  `Section`). They pair on a stem: `WidgetSection`/`WidgetList`. No
  `XListSection` names.
- **The List owns its query**, in a `protected rows()` method called from the
  shared ancestor's constructor (`ItemLoader`), which fetches `PAGE_SIZE + 1`,
  sets `$hasMore`, and trims. `toDOM()`/`toJSON()` only render what's in hand.
- A List **never** defines its own `toDOM()`; a subclass that narrows another
  List's selection **inherits** it and calls `parent::rows()` - it does not
  instantiate a sibling and borrow its `->items`.
- The **Section never queries** and is never handed items; it holds a List,
  which loads itself.

## Client mirroring

A server `HTMLObject` that needs client-side rendering gets a JavaScript twin
- a PascalCase ES module named for the class, living in `scripts/` - that
rebuilds the same DOM from the endpoint's JSON payload with the same class
names, built with real DOM methods. **All modules live under `scripts/` and
import each other by absolute URL** (`import { Api } from '/scripts/Api.js'`),
never a relative or bare specifier. **Never `innerHTML`** (XSS footgun); build
with `document.createElement` / `textContent` / `setAttribute`. Events are
delegated on `document` (`event.target.closest(...)`) so dynamically-added
content just works.

- **`scripts/main.js` is the single module entry point** (`ModuleScript` in
  `Page.php`). It doesn't hold feature code; it **lazy-`import()`s each twin
  only when that object is on the page**, keyed on the same class selector the
  twin renders (`if (document.querySelector('.Widget'))
  import('/scripts/Widget.js')`). Add a new twin by adding one such guard.
- **Shared client primitives** (not per-object) carry the cross-cutting
  behavior: `Api.js` is the one POST wrapper (attaches the CSRF header, checks
  `response.ok` before `.json()`, unwraps `data.response`, toasts on failure);
  `InfiniteScroller.js` drives every `[data-infinite-scroll]` list (each list
  type registers its renderer with `InfiniteScroller.register()`); `Search.js`
  drives a `.SearchInput`; `Toast.js`, `Dialog.js`, `FormErrors.js`,
  `ClientConfig.js`, `Cookie.js`, and `dom.js` round out the toolkit. Reach
  for these rather than re-implementing fetch/paginate/search per feature.

## Data access

- **`DB`** (`src/classes/DB.php`) wraps `mysqli`: `DB::row($sql, $class,
  $types, ...$params)` / `DB::rows(...)` fetch hydrated objects; `DB::run()`
  for writes. `DB::connection()` is the least-privilege app account;
  `DB::adminConnection()` is the DDL account (installer/migrations only).
  Prepared statements always - every literal bound, even hardcoded ones.
- **`Auth`**: `Auth::user()`, `Auth::id()`, `Auth::check()`,
  `Auth::canModerate()`, `Auth::requireLogin()`. The primary admin is
  userId 1; admin-only actions check `Auth::id() === 1` directly. **DB-backed
  search endpoints must be auth-gated** even when the underlying content is
  public - an open full-text/LIKE endpoint is a DDoS hazard.
- **Every `api/` endpoint is POST-only.** `api/api-init.php` enforces the method
  before endpoint code runs, caps the shared request body at 24 KiB, and
  accepts a flat JSON object by default. Define `API_ALLOWS_NESTED_JSON` before
  requiring it only for an endpoint that deliberately needs nested values.
- **Rate-limit** anything a stranger can make expensive (`RateLimiter`), keyed
  by client IP and - for guessable credentials - by the account too.

## i18n

Every word a person reads comes from `locales/{locale}.json` - one file per
language, read by the server (`Strings::for(self::class)`) and fetched by the
browser (`scripts/Strings.js`), never two copies. A class keeps only the
shape of a sentence; "is this class translated" has an answer anybody can
check: it contains no English (enforced by `NoEnglishInClassesTest` - add
converted classes to `tests/locale-subjects/`). The site is served in English
until the reader chooses otherwise (`LanguagePrompt` offers, never switches).
The template ships `en.json` only; add a language by adding a file.

## Coding conventions

In brief: PHP files open with `declare(strict_types=1)`; spaces around `->`;
single-quoted strings, concatenation over interpolation; 4-space indents; K&R
braces. Naming: classes and CSS classes `PascalCase` (and the same string),
methods/properties `camelCase` with capitalized acronyms, standalone
functions/locals `snake_case`, constants `SCREAMING_SNAKE_CASE`, file names
kebab-case except PascalCase class files, DB columns `camelCase`, JSON keys
`camelCase`, `data-*` attributes kebab-case. Multi-line SQL: first keyword
flush left after the opening quote, later keywords indented 4 spaces, closing
`');` flush left. Escape on output, not input. Assert on behaviour (or built
DOM via XPath), never on source text. Never put hostnames, IPs, credentials,
or deploy specifics in the repo - gitignored files only.
