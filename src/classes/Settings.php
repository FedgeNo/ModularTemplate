<?php

declare(strict_types=1);

/**
 * Admin-editable, runtime site settings, stored in the database (not .env) so
 * they can be changed from the admin panel - .env is not reliably writable once
 * an install's file permissions are locked down. Values are strings; callers
 * interpret them. Reads are cached for the request.
 */
class Settings
{
    /** @var array<string, ?string> */
    private static array $cache = [];

    public static function get(string $name, ?string $default = null): ?string
    {
        if (!array_key_exists($name, self::$cache)) {
            try {
                $stmt = DB::run('
SELECT `value`
    FROM `Settings`
    WHERE `name` = ?
', 's', $name);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);

                self::$cache[$name] = $row !== null ? $row['value'] : null;
            } catch (\mysqli_sql_exception $exception) {
                // The Settings table may not exist yet - an existing install
                // whose code was updated before the schema migration ran. This
                // is on the login/signup render path, so degrade gracefully:
                // treat every setting as unset rather than failing the whole
                // page. Cache the null so we don't re-query.
                self::$cache[$name] = null;
            }
        }

        return self::$cache[$name] ?? $default;
    }

    public static function set(string $name, string $value): void
    {
        DB::run('
INSERT INTO `Settings` (`name`, `value`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
', 'ss', $name, $value);

        self::$cache[$name] = $value;
    }
}
