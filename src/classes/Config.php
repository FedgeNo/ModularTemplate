<?php

declare(strict_types=1);

/**
 * A static accessor for config.php's returned array, loaded once and cached -
 * every call site shares the same resolved values within a request. Not a
 * global variable anything could clobber: get() is the only way in, there's
 * no public setter, and the cache is a private static property. Mirrors
 * Env's shape (get($key, $default)).
 */
class Config
{
    private static ?array $values = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();

        return self::$values[$key] ?? $default;
    }

    /**
     * Forces the next get() to re-read config.php. Only needed by a caller
     * that mutates the environment underneath it mid-process (bin/install.php
     * putenv()s freshly-written .env values into its own process right after
     * writing them, and needs config.php's Env::get() calls to pick those up
     * without waiting for a new process) - an ordinary web request never
     * mutates its own environment mid-request, so it never needs this.
     */
    public static function reload(): void
    {
        self::$values = null;
    }

    private static function load(): void
    {
        if (self::$values !== null) {
            return;
        }

        self::$values = require __DIR__ . '/../config.php';
    }
}
