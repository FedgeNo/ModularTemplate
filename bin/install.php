<?php

declare(strict_types=1);

/**
 * Interactive installer / requirements checker: `php bin/install.php`.
 *
 * On a fresh server this walks the entire install: it verifies every
 * environment prerequisite, prompts for the site and database settings,
 * provisions the database + a least-privilege runtime account + the schema,
 * and writes .env - the same steps the web setup wizard performs, minus the
 * browser.
 *
 * On an existing install it re-verifies everything, creates any missing
 * tables, and detects schema drift (columns/indexes/foreign keys that
 * schema.sql defines but the live tables lack - e.g. after upgrading),
 * offering to apply the exact ALTER statements needed.
 *
 * Every prompt is skipped when stdin isn't a terminal (CI, piped runs) -
 * in that mode it reports what it would have asked about and exits
 * non-zero, so scripts can't hang on a question nobody will answer.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Standalone helpers have no class name for the autoloader to find them by.
require __DIR__ . '/../src/functions.php';

// ---------- Output helpers ----------

function supports_color(): bool
{
    return function_exists('stream_isatty') && stream_isatty(STDOUT);
}

function color(string $text, string $code): string
{
    return supports_color() ? "\033[" . $code . 'm' . $text . "\033[0m" : $text;
}

/**
 * Word-wraps authored prose to 80 columns for terminal output, indenting
 * every continuation line by $indent so it lines up under the first line's
 * text (past a status prefix like "[WARN] "). Any whitespace already in
 * $text collapses to single spaces first, so the source can be formatted
 * however reads best and this always re-wraps it correctly regardless.
 * wordwrap()'s cut=false means a single unbreakable token longer than the
 * remaining width (a URL, a path, a command) is left on its own line rather
 * than mangled.
 */
function wrap(string $text, int $indent = 0): string
{
    $width = max(20, 80 - $indent);
    $collapsed = preg_replace('/\s+/', ' ', trim($text));

    return str_replace("\n", "\n" . str_repeat(' ', $indent), wordwrap($collapsed, $width, "\n", false));
}

function ok(string $message): void
{
    echo color('[ OK ]', '32') . ' ' . wrap($message, 7) . "\n";
}

function warn(string $message): void
{
    echo color('[WARN]', '33') . ' ' . wrap($message, 7) . "\n";
}

function fail_line(string $message): void
{
    echo color('[FAIL]', '31') . ' ' . wrap($message, 7) . "\n";
}

function fail(string $message): never
{
    fail_line($message);
    exit(1);
}

function heading(string $text): void
{
    echo "\n" . color($text, '1') . "\n";
}

// ---------- Prompt helpers ----------

function is_interactive(): bool
{
    return function_exists('stream_isatty') && stream_isatty(STDIN);
}

/**
 * Prompts until the answer passes $validate (which returns an error message
 * or null). An empty answer takes $default when one is given.
 */
function prompt(string $label, ?string $default = null, ?callable $validate = null): string
{
    while (true) {
        echo $label . ($default !== null ? ' [' . $default . ']' : '') . ': ';
        $line = fgets(STDIN);

        if ($line === false) {
            fail('stdin closed - aborting.');
        }

        $answer = trim($line);

        if ($answer === '' && $default !== null) {
            $answer = $default;
        }

        $error = $validate !== null ? $validate($answer) : null;

        if ($error === null && $answer !== '') {
            return $answer;
        }

        fail_line($error ?? 'A value is required.');
    }
}

/**
 * Like prompt(), but with terminal echo turned off (when stty is available)
 * so the password isn't displayed or left in the scrollback.
 */
function prompt_hidden(string $label): string
{
    $stty_available = function_exists('shell_exec')
        && trim((string) shell_exec('command -v stty 2>/dev/null')) !== '';

    if ($stty_available) {
        shell_exec('stty -echo');
    }

    echo $label . ': ';
    $line = fgets(STDIN);

    if ($stty_available) {
        shell_exec('stty echo');
        echo "\n";
    }

    if ($line === false) {
        fail('stdin closed - aborting.');
    }

    return trim($line);
}

function confirm(string $question): bool
{
    echo $question . ' [y/N]: ';
    $line = fgets(STDIN);

    return $line !== false && strtolower(trim($line)) === 'y';
}

// ---------- 1. Environment ----------

heading('Environment');

$environment_failures = [];

foreach (EnvironmentChecker::checks() as $name => $result) {
    if (!$result['ok']) {
        fail_line($result['message']);
        $environment_failures[$name] = $result['message'];
    } elseif ($result['warn'] ?? false) {
        warn($result['message']);
    } else {
        ok($result['message']);
    }
}

if ($environment_failures !== []) {
    echo "\n" . count($environment_failures) . ' environment problem(s) listed above - fix them and re-run this script.' . "\n";
    exit(1);
}

// ---------- 2. Configuration (.env) ----------

heading('Configuration');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!is_file(__DIR__ . '/../.env')) {
    if (!is_interactive()) {
        fail('No .env file found in the project root. Run this script in a terminal to be walked through creating one, use the web setup wizard (visit the site in a browser), or create it by hand (copy .env.example; see src/config.php for every key and its default).');
    }

    echo wrap("No .env found - let's create one. You'll need MySQL admin credentials (an account
    with CREATE/CREATE USER/GRANT privileges, e.g. root); they're used once to provision
    the database and are never stored.") . "\n\n";

    $site_url = prompt('Site URL (e.g. https://example.com)', null, function (string $value): ?string {
        return filter_var($value, FILTER_VALIDATE_URL) === false ? 'That is not a valid URL.' : null;
    });
    // No default offered: this is the site's own name, so there is nothing to
    // suggest. prompt() re-asks until it gets one.
    $site_title = prompt('Site title');
    $mail_from_address = prompt('Mail "from" address', null, function (string $value): ?string {
        return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'That is not a valid email address.' : null;
    });
    $db_host = prompt('Database host', '127.0.0.1', function (string $value): ?string {
        return preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1 ? 'The host contains invalid characters.' : null;
    });
    $db_port = prompt('Database port', '3306', function (string $value): ?string {
        return (preg_match('/^[0-9]{1,5}$/', $value) !== 1 || (int) $value < 1 || (int) $value > 65535) ? 'The port must be a number between 1 and 65535.' : null;
    });
    $db_database = prompt('Database name', 'app', function (string $value): ?string {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $value) !== 1 ? 'The name may only contain letters, numbers, and underscores.' : null;
    });

    $admin_connection = null;

    // On a fresh MariaDB/MySQL the root account usually authenticates through
    // the local unix socket with no password rather than over TCP - so when
    // this runs as root (e.g. under sudo), connect that way automatically
    // instead of failing on a password. The runtime account is created for
    // host '%' regardless (see Installer::provisionDatabase), so provisioning
    // over the socket rather than $db_host makes no difference.
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        try {
            $admin_connection = mysqli_connect('localhost', 'root', '');
            ok('Connected to the database as root through the local socket (no admin password needed).');
        } catch (\mysqli_sql_exception $exception) {
            // root has a password set, or a non-standard socket - fall back to asking.
        }
    }

    while ($admin_connection === null) {
        $admin_username = prompt('Database admin username', 'root');
        $admin_password = prompt_hidden('Database admin password');

        try {
            $admin_connection = mysqli_connect($db_host, $admin_username, $admin_password, null, (int) $db_port);
        } catch (\mysqli_sql_exception $exception) {
            fail_line('Could not connect: ' . $exception -> getMessage());
            echo '       ' . wrap('On a fresh MariaDB/MySQL, root only authenticates over the local socket -
            re-run under sudo, or use a password admin account. Try again.', 7) . "\n";
        }
    }

    try {
        $runtime_account = Installer::provisionDatabase($admin_connection, $db_database, [
            Mailer::FROM_ADDRESS_SETTING => $mail_from_address,
        ]);
    } catch (\mysqli_sql_exception $exception) {
        fail('Database setup failed: ' . $exception -> getMessage());
    }

    mysqli_close($admin_connection);
    ok('database, runtime account, and schema provisioned');

    $env_values = [
        'DB_HOST' => $db_host,
        'DB_PORT' => $db_port,
        'DB_DATABASE' => $db_database,
        'DB_USERNAME' => $runtime_account['username'],
        'DB_PASSWORD' => $runtime_account['password'],
        'SITE_URL' => $site_url,
        'SITE_TITLE' => $site_title,
    ];

    // .env holds DB_PASSWORD. Create it 0600 from the start (umask 0077 →
    // new file mode 0600) so there's no window where it lands group/world-
    // readable before a later chmod - file_put_contents would otherwise
    // create it at the default umask first.
    $env_path = __DIR__ . '/../.env';
    $previous_umask = umask(0077);
    $env_written = file_put_contents($env_path, Installer::envContents($env_values));
    umask($previous_umask);

    if ($env_written === false) {
        fail('Could not write .env - check that ' . realpath(__DIR__ . '/..') . ' is writable by this user.');
    }

    // Belt-and-suspenders for the re-run case (umask doesn't tighten an
    // already-existing file) - and a failure here is fatal, not swallowed,
    // since a world-readable .env leaks the DB password.
    if (!chmod($env_path, 0600)) {
        fail('Wrote .env but could not restrict it to 0600 - it holds DB_PASSWORD. Fix manually: chmod 600 ' . realpath($env_path));
    }

    // The web server reads .env too - hand the file to its account when this
    // ran as root (a root-owned 0600 file is unreadable to it).
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $web_uid = EnvironmentChecker::webServerUid();

        if ($web_uid !== null) {
            @chown($env_path, $web_uid);
        }
    }

    ok('.env written');

    // getenv() itself always reads live, but config.php's computed array is
    // only recomputed when it's required again - push the values just written
    // into the running process directly, then force Config to recompute from
    // them rather than keep serving whatever it cached before this ran.
    foreach ($env_values as $key => $value) {
        putenv($key . '=' . $value);
    }

    Config::reload();
}

// The checks below reach the database through Settings. Prove the runtime
// connection works first so bad .env credentials surface as this friendly
// message, rather than an uncaught mysqli_sql_exception thrown from deep
// inside a Settings::set().
try {
    DB::connection();
} catch (\mysqli_sql_exception $exception) {
    fail('Could not connect to the database with the credentials in .env: ' . $exception -> getMessage());
}

// No usable mail "from" address anywhere yet (a fresh install skips this -
// the wizard above already collected and stored one). Store in Settings when
// possible; otherwise - no TTY to ask in - this just keeps prompting on every
// future interactive run until an admin actually answers it, rather than
// silently leaving mail broken forever.
if ((string) Settings::get(Mailer::FROM_ADDRESS_SETTING, '') === '' && is_interactive()) {
    $new_mail_from_address = prompt('Mail "from" address (no email can be sent until this is set)', null, function (string $value): ?string {
        return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'That is not a valid email address.' : null;
    });
    Settings::set(Mailer::FROM_ADDRESS_SETTING, $new_mail_from_address);
    ok('mail "from" address saved');
}

if ((string) Config::get('siteURL') === '') {
    fail('SITE_URL is not set in .env - every generated link needs it.');
}

if ((string) Config::get('siteTitle') === '') {
    fail('SITE_TITLE is not set in .env - the site has no name for its page titles or its outgoing email. Set it to whatever you call this site; there is no default.');
}

// HTTPS is a requirement, not a preference: an http:// SITE_URL is a
// dealbreaker. The site itself refuses to serve over plain HTTP (everything
// 301s to https, and an http SITE_URL gets a config-error page), so an
// install without TLS simply doesn't work - get the certificate first.
if (!str_starts_with((string) Config::get('siteURL'), 'https://')) {
    fail('SITE_URL is ' . Config::get('siteURL') . ' - this app requires HTTPS and will not serve over plain HTTP. Set up TLS first, then set SITE_URL to the https:// URL. For a real domain use Let\'s Encrypt (certbot); for localhost use a locally-trusted certificate (mkcert) or your distribution\'s self-signed default. See README.md\'s HTTPS section.');
}

// The check above only proves SITE_URL *says* https:// - it's just a string,
// not proof anything is actually listening with a working certificate. Test
// for real: connect to the site's own hostname over HTTPS and see what
// happens. Not 127.0.0.1 - a VirtualHost setup routes by the Host header
// (SNI for TLS), so the loopback address may not reach this site at all;
// the real hostname is what actually has to work.
$site_host = (string) (parse_url(Config::get('siteURL'), PHP_URL_HOST) ?: 'your-domain');
$site_port = parse_url(Config::get('siteURL'), PHP_URL_PORT);
$server_name_value = $site_host . ($site_port !== null ? ':' . $site_port : '');

$https_serving = EnvironmentChecker::httpsServing($server_name_value);

if ($https_serving === false) {
    fail('SITE_URL is https://... but a real HTTPS connection to ' . $server_name_value . ' failed at the TLS handshake itself - something is listening on the port without actually serving TLS. Check that the web server\'s SSL certificate/key are set and mod_ssl (or equivalent) is loaded, then re-run.');
} elseif ($https_serving === true) {
    ok('HTTPS confirmed live (a real connection to ' . $server_name_value . ' over TLS succeeded)');
} else {
    warn('Could not confirm HTTPS live by connecting to ' . $server_name_value . ' - inconclusive (DNS may not point here yet, a firewall, or the web server isn\'t up yet). Verify by visiting ' . Config::get('siteURL') . ' in a browser.');
}

// ---------- HTTPS host-spoofing guard ----------

// By default, Apache builds SERVER_NAME (and mod_rewrite's %{SERVER_NAME}) -
// and hence the target of the HTTPS redirect in .htaccess - from whatever
// Host header the request arrived with, not from a fixed configured name.
// That means anyone can forge a Host header and get 301-redirected to a
// domain of their choosing instead of this site - a phishing/cache-poisoning
// primitive. The fix is two Apache directives that make Apache ignore the
// client-supplied Host header for this purpose:
//   ServerName <host>[:<port>]
//   UseCanonicalName On
// Rather than just ask whether ServerName/UseCanonicalName are set, prove it:
// send a request to the real hostname with a deliberately forged Host header
// and check whether the redirect reflects it.
$spoof_test = EnvironmentChecker::hostHeaderSpoofable($site_host);

if ($spoof_test === true) {
    fail('The HTTPS redirect can be spoofed via a forged Host header (confirmed live: a request with a fake Host '
        . 'header got redirected to that same fake host) - anyone can 301 a victim to a domain of their choosing. '
        . 'Set "ServerName ' . $server_name_value . '" and "UseCanonicalName On" in the web server\'s config '
        . '(httpd.conf\'s top level if you\'re not using a <VirtualHost>, or inside the vhost if you are), then '
        . 're-run. See README.md\'s HTTPS section.');
} elseif ($spoof_test === false) {
    ok('ServerName + UseCanonicalName confirmed live (a forged Host header was not reflected in the redirect)');
} else {
    // Couldn't prove it either way (no reachable response, or no redirect to
    // inspect) - fall back to asking.
    $server_name_question = wrap('Could not confirm this live. Have you set "ServerName ' . $server_name_value . '" and '
        . '"UseCanonicalName On" in the web server\'s config (httpd.conf\'s top level if you\'re not using a '
        . '<VirtualHost>, or inside the vhost if you are)?');

    if (is_interactive()) {
        if (!confirm($server_name_question)) {
            fail('Set "ServerName ' . $server_name_value . '" and "UseCanonicalName On" in the web server\'s config first - '
                . 'required so the HTTPS redirect can\'t be spoofed via a forged Host header. See README.md\'s HTTPS section.');
        }

        echo "\n";
        ok('ServerName + UseCanonicalName confirmed');
    } elseif (Env::get('SERVERNAME_CONFIRMED', '') === '1') {
        ok('ServerName + UseCanonicalName confirmed (SERVERNAME_CONFIRMED=1)');
    } else {
        fail('Cannot confirm interactively (no terminal), and could not verify it live. Set "ServerName ' . $server_name_value . '" and '
            . '"UseCanonicalName On" in the web server\'s config, then set SERVERNAME_CONFIRMED=1 to continue non-interactively. '
            . 'See README.md\'s HTTPS section.');
    }
}

// ---------- 3. Database connection ----------

heading('Database');

try {
    $mysqli = mysqli_connect(Config::get('host'), Config::get('username'), Config::get('password'), Config::get('database'), Config::get('port'));
} catch (\mysqli_sql_exception $exception) {
    fail('Could not connect to the database with the credentials in .env: ' . $exception -> getMessage());
}

ok('database connection works (' . Config::get('username') . '@' . Config::get('host') . ':' . Config::get('port') . '/' . Config::get('database') . ')');

// ---------- 4. Schema ----------

/**
 * The app's own runtime DB user is intentionally least-privilege and can't
 * CREATE/ALTER, so schema work needs a separately-supplied admin connection.
 * DB::adminConnection() covers the two non-interactive strategies (root's
 * unix_socket auth, or DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD from the
 * environment) and is the single source of truth for both of those -
 * everything below is what only a CLI script can add on top: explicit
 * DB_ADMIN_USERNAME/PASSWORD that are actually wrong fail loudly here rather
 * than silently falling through to a prompt (which would look identical to
 * the env vars simply never having been set), and an interactive prompt is
 * the last resort when neither strategy works. Only requested when there's
 * actual schema work to do.
 *
 * The handle is DB::adminConnection()'s cached singleton, so every step that
 * asks shares one connection - a caller must never mysqli_close() it, or the
 * next step gets the dead handle back and every query on it throws.
 */
function admin_connection(string $needed_for): ?\mysqli
{
    $connection = DB::adminConnection();

    if ($connection !== null) {
        return $connection;
    }

    if (Env::get('DB_ADMIN_USERNAME') !== null && Env::get('DB_ADMIN_PASSWORD') !== null) {
        fail('Could not connect with the DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD credentials.');
    }

    if (!is_interactive()) {
        return null;
    }

    echo "\nMySQL admin credentials are needed to " . $needed_for . " (used once, never stored).\n";

    while (true) {
        $admin_username = prompt('Database admin username', 'root');
        $admin_password = prompt_hidden('Database admin password');

        try {
            return mysqli_connect(Config::get('host'), $admin_username, $admin_password, Config::get('database'), Config::get('port'));
        } catch (\mysqli_sql_exception $exception) {
            fail_line('Could not connect: ' . $exception -> getMessage() . ' - try again.');
        }
    }
}

// A fresh database (none of the app's tables yet) gets the current schema
// created directly and skips the incremental upgrade steps (drift, index/type
// migrations) below - those only apply to an already-installed database.
// Computed before any table is created. An empty-but-installed DB (tables
// present, no rows) is NOT fresh and takes the full upgrade path.
$fresh_install = SchemaInstaller::isFreshInstall();

try {
    $missing_statements = SchemaInstaller::missingTables($mysqli);
} catch (\RuntimeException $exception) {
    fail($exception -> getMessage());
}

if ($missing_statements === []) {
    ok('all tables already exist');
} else {
    $admin_mysqli = admin_connection('create ' . count($missing_statements) . ' missing table(s): ' . implode(', ', array_keys($missing_statements)));

    if ($admin_mysqli === null) {
        fail(
            count($missing_statements) . ' table(s) are missing ('
            . implode(', ', array_keys($missing_statements)) . '). '
            . 'Create them either by running `mysql -u <admin> -p ' . Config::get('database') . ' < schema.sql` yourself, '
            . 'or by setting DB_ADMIN_USERNAME and DB_ADMIN_PASSWORD (an account with CREATE privileges) before re-running this script.'
        );
    }

    try {
        SchemaInstaller::createTables($admin_mysqli, $missing_statements);
    } catch (\mysqli_sql_exception $exception) {
        fail('Failed to create missing table(s): ' . $exception -> getMessage());
    }

    ok('created ' . count($missing_statements) . ' missing table(s): ' . implode(', ', array_keys($missing_statements)));
}

// ---------- 5. Schema drift ----------

// Foreign keys are pulled out of the drift here and applied after the
// index/type migrations below - a column a new FK references may need a type
// migration first before the FK can be created.
$deferred_foreign_keys = [];
$drift = $fresh_install ? [] : SchemaInstaller::missingDefinitions();

if ($fresh_install) {
    ok('fresh install - current schema created directly, no drift to reconcile');
} elseif ($drift === []) {
    ok('existing tables match schema.sql (no missing columns, indexes, or foreign keys)');
} else {
    $labels = [];

    foreach ($drift as $table => $alters) {
        foreach (array_keys($alters) as $label) {
            $labels[] = $table . ': ' . $label;
        }
    }

    warn('existing tables are missing ' . count($labels) . ' definition(s) from schema.sql:');

    foreach ($labels as $label) {
        echo '       - ' . $label . "\n";
    }

    $apply = false;
    $admin_mysqli = null;

    // A non-interactive root run applies the drift straight away -
    // admin_connection() authenticates over the unix socket with no
    // credentials, the same way it creates missing tables above; an
    // interactive run confirms first. Letting admin_connection() decide (it
    // returns null when it genuinely can't get admin access) is what keeps a
    // scripted root deploy from silently skipping the drift, which a pre-gate
    // on is_interactive()/DB_ADMIN would do.
    if (!is_interactive() || confirm('Apply the ALTER statements to bring them up to date?')) {
        $admin_mysqli = admin_connection('apply the missing definitions');
        $apply = $admin_mysqli !== null;
    }

    if ($apply) {
        $failed = 0;

        foreach ($drift as $table => $alters) {
            foreach ($alters as $label => $alter) {
                // Defer foreign keys until after the index/type migrations.
                if (str_starts_with($label, 'foreign key ')) {
                    $deferred_foreign_keys[$table . ': ' . $label] = $alter;

                    continue;
                }

                try {
                    mysqli_query($admin_mysqli, $alter);
                    ok('applied ' . $table . ': ' . $label);
                } catch (\mysqli_sql_exception $exception) {
                    $failed++;
                    fail_line('could not apply ' . $table . ': ' . $label . ' - ' . $exception -> getMessage());
                }
            }
        }

        if ($failed > 0) {
            fail($failed . ' ALTER(s) failed - see above. The statements come straight from schema.sql; apply them manually once the cause is fixed.');
        }
    } else {
        fail_line('not applied - the app may misbehave until the schema is brought up to date. The exact statements:');

        foreach ($drift as $alters) {
            foreach ($alters as $alter) {
                echo '       ' . $alter . ";\n";
            }
        }

        echo "\n" . wrap('Apply them as a MySQL admin (or re-run this script interactively / with
        DB_ADMIN_USERNAME and DB_ADMIN_PASSWORD set) and re-run.') . "\n";
        exit(1);
    }
}

// ---------- 6. Index migrations + maintenance ----------

// schema.sql can carry idempotent index migrations (ALTER TABLE ... ADD/DROP
// INDEX IF NOT EXISTS/IF EXISTS, MODIFY COLUMN type fixes) - DDL, so unlike
// the plain-DML maintenance these need admin privileges the runtime account
// deliberately doesn't have. Only reach for admin credentials when one is
// actually still needed (an already-applied migration is a no-op and
// shouldn't force a prompt on every healthy re-run - same principle as the
// schema drift step).
$needed_index_migrations = $fresh_install ? [] : SchemaInstaller::neededIndexMigrations();

if ($fresh_install) {
    ok('fresh install - no index/type migrations to apply');
} elseif ($needed_index_migrations === [] && $deferred_foreign_keys === []) {
    ok('index migrations up to date (nothing to apply)');
} else {
    $pending_count = count($needed_index_migrations) + count($deferred_foreign_keys);
    $index_admin_mysqli = admin_connection('apply ' . $pending_count . ' pending migration(s) from schema.sql');

    if ($index_admin_mysqli === null) {
        fail(
            $pending_count . ' migration(s) from schema.sql are still pending: '
            . implode('; ', array_merge($needed_index_migrations, array_values($deferred_foreign_keys))) . '. '
            . 'Apply them as a MySQL admin, or set DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD and re-run.'
        );
    }

    foreach ($needed_index_migrations as $statement) {
        try {
            mysqli_query($index_admin_mysqli, $statement);
            ok('applied: ' . $statement);
        } catch (\mysqli_sql_exception $exception) {
            fail('Failed to apply index migration (' . $statement . '): ' . $exception -> getMessage());
        }
    }

    // Then the foreign keys deferred from the drift step, now that the columns
    // they reference have their final types.
    foreach ($deferred_foreign_keys as $label => $alter) {
        try {
            mysqli_query($index_admin_mysqli, $alter);
            ok('applied ' . $label);
        } catch (\mysqli_sql_exception $exception) {
            fail('Failed to apply foreign key (' . $label . '): ' . $exception -> getMessage());
        }
    }
}

// Idempotent DML upkeep defined alongside the DDL in schema.sql - run on the
// runtime connection, which has the privileges it needs, now that the tables
// are known-good.
SchemaInstaller::runMaintenance($mysqli);
ok('schema.sql maintenance applied');

// Record the code version the database now matches - init.php locks the site
// to a maintenance page while the two disagree, so this is what unlocks it
// after an upgrade. The runtime connection can write it (plain INSERT/UPDATE).
$version_name = 'appVersion';
$code_version = Installer::codeVersion();
$version_stmt = mysqli_prepare($mysqli, '
INSERT INTO `Settings` (`name`, `value`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
');
mysqli_stmt_bind_param($version_stmt, 'ss', $version_name, $code_version);

try {
    mysqli_stmt_execute($version_stmt);
} catch (\mysqli_sql_exception $exception) {
    fail('Could not record the database version: ' . $exception -> getMessage());
}

ok('database marked as version ' . $code_version);

// ---------- Done ----------

echo "\n" . color('All checks passed.', '1;32') . "\n\n";
echo "Next steps:\n";
echo '  1. ' . wrap('Visit ' . Config::get('siteURL') . ' and sign up - the first account created becomes
the site\'s administrator.', 5) . "\n";
