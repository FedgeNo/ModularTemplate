<?php

declare(strict_types=1);

class DB
{
    private static ?\mysqli $connection = null;
    private static ?\mysqli $adminConnection = null;
    private static bool $adminConnectionAttempted = false;

    public static function connection(): \mysqli
    {
        if (self::$connection === null) {
            self::$connection = mysqli_connect(
                Config::get('host'),
                Config::get('username'),
                Config::get('password'),
                Config::get('database'),
                Config::get('port')
            );

            mysqli_set_charset(self::$connection, 'utf8mb4');

            // PHP's own clock (date.timezone) is UTC - without this, MySQL's
            // NOW()/CURRENT_TIMESTAMP() run on the server's SYSTEM timezone
            // instead, which silently disagrees with PHP by however many
            // hours the server happens to be offset from UTC. Pinning the
            // session here makes MySQL's clock the same clock PHP uses
            // everywhere else, rather than needing every table/query to
            // account for a possible mismatch.
            $time_zone = '+00:00';

            self::run('
SET `time_zone` = ?
', 's', $time_zone);
        }

        return self::$connection;
    }

    /**
     * A connection with DDL privileges the app's own least-privilege runtime
     * user deliberately doesn't have - needed for schema changes (CREATE/
     * ALTER TABLE and friends). Two non-interactive strategies, tried in
     * order and cached (including a null result, so a caller that asks
     * again later in the same process doesn't retry a doomed attempt):
     *
     *  1. MariaDB/MySQL's unix_socket auth plugin, which authenticates the
     *     OS root user as the DB root user with no password at all - but
     *     only over the local socket, so this only works when the current
     *     process is actually running as root (a `sudo php bin/install.php`
     *     run). Passing 'localhost' literally (not Config::get('host')) is
     *     what makes mysqli use the socket instead of TCP.
     *  2. DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD from the environment, when set.
     *
     * Returns null when neither is available - deliberately never prompts:
     * that's a CLI-only concern (bin/install.php falls back to an
     * interactive prompt itself when this comes back empty), not something
     * that belongs in a general-purpose data-layer class a web request could
     * also reach through Installer::attemptSilentUpgrade().
     */
    public static function adminConnection(): ?\mysqli
    {
        if (self::$adminConnectionAttempted) {
            return self::$adminConnection;
        }

        self::$adminConnectionAttempted = true;

        if (trim((string) @shell_exec('id -u 2>/dev/null')) === '0') {
            try {
                self::$adminConnection = mysqli_connect('localhost', 'root', '', Config::get('database'));

                return self::$adminConnection;
            } catch (\mysqli_sql_exception $exception) {
                // Not every root box has unix_socket auth configured for the
                // DB root account (or even a DB root account by that name) -
                // fall through to the credential-based path below.
            }
        }

        $admin_username = Env::get('DB_ADMIN_USERNAME');
        $admin_password = Env::get('DB_ADMIN_PASSWORD');

        if ($admin_username === null || $admin_password === null) {
            return null;
        }

        try {
            self::$adminConnection = mysqli_connect(Config::get('host'), $admin_username, $admin_password, Config::get('database'), Config::get('port'));
        } catch (\mysqli_sql_exception $exception) {
            return null;
        }

        return self::$adminConnection;
    }

    public static function prepare(string $sql): \mysqli_stmt
    {
        return mysqli_prepare(self::connection(), $sql);
    }

    public static function bind(\mysqli_stmt $stmt, string $types, mixed ...$params): void
    {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    /**
     * How many statements this request has run.
     *
     * Counted so a page can say so - a number nobody watches is how a listing
     * quietly grows a query per row. Kept on execute() rather than on run(),
     * so a statement prepared once and executed in a loop counts every time
     * it actually goes to the server.
     */
    private static int $queries = 0;

    public static function queryCount(): int
    {
        return self::$queries;
    }

    public static function execute(\mysqli_stmt $stmt): void
    {
        self::$queries++;

        mysqli_stmt_execute($stmt);
    }

    public static function run(string $sql, ?string $types = null, mixed ...$params): \mysqli_stmt
    {
        $stmt = self::prepare($sql);

        if ($types !== null) {
            self::bind($stmt, $types, ...$params);
        }

        self::execute($stmt);

        return $stmt;
    }

    public static function row(string $sql, string $class, ?string $types = null, mixed ...$params): ?object
    {
        $result = mysqli_stmt_get_result(self::run($sql, $types, ...$params));
        $row = mysqli_fetch_object($result, $class);

        return $row === false ? null : $row;
    }

    public static function rows(string $sql, string $class, ?string $types = null, mixed ...$params): array
    {
        $result = mysqli_stmt_get_result(self::run($sql, $types, ...$params));
        $rows = [];

        while (($row = mysqli_fetch_object($result, $class)) !== null && $row !== false) {
            $rows[] = $row;
        }

        return $rows;
    }
}
