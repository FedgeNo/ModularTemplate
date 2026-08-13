<?php

declare(strict_types=1);

class Env
{
    private static bool $loaded = false;
    private static bool $unreadable = false;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    /**
     * Whether there is a .env that this process cannot read. Distinct from
     * there being none: a site with no .env is simply not installed yet, while
     * one whose .env is unreadable is installed and answering every request
     * with placeholder configuration - a different problem, and one nothing
     * downstream can tell apart without asking.
     */
    public static function unreadable(): bool
    {
        self::load();

        return self::$unreadable;
    }

    public static function path(): string
    {
        return __DIR__ . '/../../.env';
    }

    private static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        $path = self::path();

        if (!is_file($path)) {
            return;
        }

        // A .env this process can't read (a root-tightened file, a CLI run as
        // another user) reads as "no overrides" rather than a warning storm -
        // file() returns false on failure and foreach would warn on it. It is
        // recorded rather than shrugged off, because every value downstream is
        // then a placeholder and a caller has to be able to say so.
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            self::$unreadable = true;

            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = self::stripQuotes(trim($value));

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }

    /**
     * A hand-edited .env is easy to write as KEY="value" or KEY='value' out
     * of habit from other tools - strip one matching pair of surrounding
     * quotes so the literal quote characters don't end up part of the value
     * itself (e.g. a wrapped WS_SECRET would otherwise never match what the
     * WebSocket daemon reads from the same file). Public so
     * Installer::envContents() can strip any pre-existing surrounding quotes
     * from a value before adding its own - same rule, both directions, so the
     * round trip is stable instead of guessing.
     */
    public static function stripQuotes(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (($first === '"' && $last === '"') || ($first === '\'' && $last === '\'')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
