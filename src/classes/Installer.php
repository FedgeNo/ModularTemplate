<?php

declare(strict_types=1);

/**
 * The provisioning steps shared by the two install paths (the web setup
 * wizard in src/setup.php and the interactive CLI in bin/install.php):
 * creating the database, a least-privilege runtime account, the schema,
 * and the .env contents that record all of it.
 */
class Installer
{
    /**
     * Creates the database (if missing), a least-privilege runtime account
     * with a freshly generated random password, grants it SELECT/INSERT/
     * UPDATE/DELETE on that database only, and creates every table from
     * schema.sql that doesn't already exist.
     *
     * @param array<string, string> $initial_settings name => value rows to seed
     *                                                 into the Settings table
     * @return array{username: string, password: string} the runtime account credentials
     */
    public static function provisionDatabase(\mysqli $admin_connection, string $db_database, array $initial_settings = []): array
    {
        // CREATE DATABASE / CREATE USER / GRANT can't be prepared (MySQL's
        // prepared-statement protocol doesn't cover those statement types), so
        // the database name is interpolated below. Both callers already
        // validate it, but enforce it here too so the interpolation is safe no
        // matter who calls this.
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db_database)) {
            throw new \RuntimeException('Invalid database name - only letters, numbers, and underscores are allowed.');
        }

        $runtime_username = $db_database;
        $runtime_password = bin2hex(random_bytes(24));

        mysqli_query($admin_connection, '
CREATE DATABASE IF NOT EXISTS `' . $db_database . '`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
');
        mysqli_select_db($admin_connection, $db_database);

        // CREATE USER/GRANT can't go through mysqli_prepare - account-management
        // statements aren't supported by the prepared-statement protocol on most
        // server versions - so the (self-generated, never user-supplied) password
        // is escaped and interpolated directly instead of bound as a placeholder.
        // The password stays raw in $runtime_password; it's escaped inline at each
        // query (the last possible step) rather than into a variable, so nobody
        // later reuses a pre-escaped value or feeds the raw one straight in.
        // Scoped to host '%' rather than the connect host - that's the address the
        // app connects to the server as, not necessarily what the server resolves
        // the connecting client back to for grant-matching (e.g. a loopback TCP
        // connection can resolve to 'localhost' regardless of the host string used
        // to reach it).
        mysqli_query($admin_connection, '
CREATE USER IF NOT EXISTS \'' . $runtime_username . '\'@\'%\'
    IDENTIFIED BY \'' . mysqli_real_escape_string($admin_connection, $runtime_password) . '\'
');

        // CREATE USER IF NOT EXISTS is a no-op when the account already exists
        // (a reinstall), so its password would silently stay the old one while
        // a fresh one gets written to .env - leaving the site unable to connect.
        // ALTER USER forces the password to match what we're about to store.
        mysqli_query($admin_connection, '
ALTER USER \'' . $runtime_username . '\'@\'%\'
    IDENTIFIED BY \'' . mysqli_real_escape_string($admin_connection, $runtime_password) . '\'
');
        mysqli_query($admin_connection, '
GRANT SELECT, INSERT, UPDATE, DELETE
    ON `' . $db_database . '`.*
    TO \'' . $runtime_username . '\'@\'%\'
');

        SchemaInstaller::createTables($admin_connection, SchemaInstaller::missingTables($admin_connection));
        SchemaInstaller::runMaintenance($admin_connection);

        // Record the code version this database now matches - init.php refuses
        // to serve a mismatched pair, so a fresh install must start in agreement.
        $initial_settings['appVersion'] = self::codeVersion();

        // Seed any initial settings (e.g. the mail from-address entered in
        // setup) now that the Settings table exists. The admin connection runs this - the
        // runtime account and the DB::connection() singleton aren't set up
        // yet at install time.
        foreach ($initial_settings as $setting_name => $setting_value) {
            $stmt = mysqli_prepare($admin_connection, '
INSERT INTO `Settings` (`name`, `value`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
');
            mysqli_stmt_bind_param($stmt, 'ss', $setting_name, $setting_value);
            mysqli_stmt_execute($stmt);
        }

        return ['username' => $runtime_username, 'password' => $runtime_password];
    }

    /**
     * The codebase's version, hard-coded in src/init.php (the single source of
     * truth). Web requests have init.php loaded so the constant exists; the CLI
     * installer doesn't load init.php (it can't - init.php starts a session and
     * sends headers), so fall back to reading the constant out of the source.
     */
    public static function codeVersion(): string
    {
        if (defined('APP_VERSION')) {
            return APP_VERSION;
        }

        $init_source = (string) file_get_contents(__DIR__ . '/../init.php');

        if (!preg_match('/const APP_VERSION = \'([^\']+)\';/', $init_source, $match)) {
            throw new \RuntimeException('Could not find APP_VERSION in src/init.php.');
        }

        return $match[1];
    }

    /** How long an unsuccessful upgrade stands as the answer for. */
    private const UPGRADE_RETRY_SECONDS = 60;

    /**
     * Whether to read the schema again, or let the last attempt stand.
     *
     * What stops a silent upgrade is still true a moment later: typically no
     * account holding the privileges for DDL. Left
     * unguarded, every request arriving while the site is behind reads the
     * whole schema to be told that again, which is worst exactly when the site
     * is already struggling. Trying once a minute picks up an admin fixing the
     * reason soon enough, and leaves the maintenance page costing one query.
     *
     * The marker is keyed to this installation and this version of the code,
     * so pulling a new one is tried at once rather than waited out.
     */
    private static function mayAttemptUpgrade(): bool
    {
        $marker = sys_get_temp_dir() . '/app-upgrade-' . hash('sha256', __DIR__ . '|' . self::codeVersion());
        $attempted = is_file($marker) ? (int) filemtime($marker) : 0;

        if (time() - $attempted < self::UPGRADE_RETRY_SECONDS) {
            return false;
        }

        // Before the attempt rather than after it, so the requests arriving
        // during a slow migration wait for it instead of starting their own.
        touch($marker);

        return true;
    }

    /**
     * The version the database says it was installed at, or null where it has
     * never been stamped with one.
     *
     * Read straight rather than through Settings, which answers a query that
     * failed with "unset" so a login page still renders. This one is the gate
     * every request passes: a database that cannot be asked what version it is
     * is a server fault, and reading it as an old version would answer every
     * visitor with a maintenance page saying to run an installer that would
     * find nothing wrong.
     *
     * @throws \mysqli_sql_exception
     */
    public static function recordedVersion(): ?string
    {
        $setting = 'appVersion';

        try {
            $result = mysqli_stmt_get_result(DB::run('
SELECT `value`
    FROM `Settings`
    WHERE `name` = ?
', 's', $setting));
        } catch (\mysqli_sql_exception $exception) {
            // A database with none of the tables in it is a database waiting to
            // be installed, which the upgrade below knows how to do - so it
            // reads as no version rather than as a fault. Anything else the
            // database has to say is a fault.
            if ($exception -> getCode() !== self::NO_SUCH_TABLE) {
                throw $exception;
            }

            return null;
        }

        $row = $result === false ? null : mysqli_fetch_assoc($result);

        return $row === null ? null : (string) $row['value'];
    }

    /** MySQL's ER_NO_SUCH_TABLE. */
    private const NO_SUCH_TABLE = 1146;

    /**
     * Called from init.php's version gate on every request while the database
     * is behind APP_VERSION - a separate concern from the fresh-install
     * wizard in src/setup.php, not a step of it. Applies whatever's pending
     * (missing tables, schema drift, index migrations, DML maintenance) and
     * stamps the new version, silently, with no page of its own - the request
     * just keeps going once it returns true. DDL needs privileges the runtime
     * account deliberately doesn't have, so that part only runs when
     * DB::adminConnection() can actually get one - in practice that means
     * DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD set in the environment, since a web
     * request never runs as root; without it, a request with genuine DDL
     * pending returns false and init.php falls back to its existing
     * maintenance page. Every statement involved (CREATE TABLE IF NOT EXISTS,
     * ADD/DROP INDEX IF (NOT) EXISTS, INSERT ... ON DUPLICATE KEY) is
     * idempotent, so this is safe to run from multiple concurrent requests
     * with no coordination.
     *
     * Runs with ignore_user_abort() forced on for its duration: this can be
     * triggered by any visitor's ordinary page load, and a migration that's
     * slower than their patience shouldn't get torn down mid-ALTER just
     * because they navigated away or closed the tab - it needs to reach the
     * version stamp at the end (or fail cleanly) regardless of whether
     * anyone is still there to receive the response.
     */
    public static function attemptSilentUpgrade(): bool
    {
        if (!self::mayAttemptUpgrade()) {
            return false;
        }

        $previous_ignore_user_abort = ignore_user_abort(true);

        try {
            try {
                // A fresh database (none of the app's tables yet) gets the
                // current schema created directly - the incremental drift/type
                // migrations only make sense against an already-installed
                // database, so they're skipped here.
                $fresh = SchemaInstaller::isFreshInstall();
                $missing_tables = SchemaInstaller::missingTables(DB::connection());
            } catch (\mysqli_sql_exception | \RuntimeException $exception) {
                return false;
            }

            if ($missing_tables !== []) {
                $admin_connection = DB::adminConnection();

                if ($admin_connection === null) {
                    return false;
                }

                try {
                    SchemaInstaller::createTables($admin_connection, $missing_tables);
                } catch (\mysqli_sql_exception $exception) {
                    return false;
                }
            }

            try {
                $drift = $fresh ? [] : SchemaInstaller::missingDefinitions();
                $needed_index_migrations = $fresh ? [] : SchemaInstaller::neededIndexMigrations();
            } catch (\mysqli_sql_exception | \RuntimeException $exception) {
                return false;
            }

            if ($drift !== [] || $needed_index_migrations !== []) {
                $admin_connection = DB::adminConnection();

                if ($admin_connection === null) {
                    return false;
                }

                try {
                    // Apply the drift in the right order for an old database:
                    // columns and indexes first, THEN the index/type migrations
                    // (which unsign old signed-int id columns), THEN the foreign
                    // keys. A new FK from a column an old DB still has as signed
                    // int(11) to an unsigned key fails to create (errno 150), so
                    // the unsigning MODIFYs must run before those FK adds.
                    foreach ($drift as $alters) {
                        foreach ($alters as $label => $alter) {
                            if (!str_starts_with($label, 'foreign key ')) {
                                mysqli_query($admin_connection, $alter);
                            }
                        }
                    }

                    foreach ($needed_index_migrations as $statement) {
                        mysqli_query($admin_connection, $statement);
                    }

                    foreach ($drift as $alters) {
                        foreach ($alters as $label => $alter) {
                            if (str_starts_with($label, 'foreign key ')) {
                                mysqli_query($admin_connection, $alter);
                            }
                        }
                    }
                } catch (\mysqli_sql_exception $exception) {
                    return false;
                }
            }

            SchemaInstaller::runMaintenance(DB::connection());
            Settings::set('appVersion', self::codeVersion());

            return true;
        } finally {
            ignore_user_abort($previous_ignore_user_abort === 1);
        }
    }

    /**
     * @param array<string, string> $env ENV key => value, in the order they
     *                                    should appear in the file
     */
    public static function envContents(array $env): string
    {
        $lines = [];

        foreach ($env as $key => $value) {
            // Always quoted, and any quote characters the value already
            // started/ended with are stripped first (Env::stripQuotes() - the
            // same rule Env::load() applies on read) - so re-writing a value
            // that already looks quoted never doubles up, and the write/read
            // round trip is stable instead of ambiguous.
            $lines[] = $key . '="' . Env::stripQuotes($value) . '"';
        }

        return implode("\n", $lines) . "\n";
    }
}
