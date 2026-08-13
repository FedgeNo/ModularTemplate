<?php

declare(strict_types=1);

/**
 * Builds and tears down the throwaway database DatabaseTestCase-derived
 * tests run against. Needs the DB server's root account - only reachable via
 * MariaDB/MySQL's unix_socket auth plugin, which authenticates the OS root
 * user with no password over the local socket (mysqli_connect('localhost',
 * 'root', '') - the same trick bin/install.php's admin_connection() uses) -
 * so this only works when bin/run-tests.php itself runs as root (`sudo php
 * bin/run-tests.php`).
 *
 * Built by replaying schema.sql's CREATE TABLE statements - the same text the
 * installer trusts - so the tests enforce exactly what a fresh install
 * enforces, foreign keys included. (It used to copy structure off the live
 * database with CREATE TABLE ... LIKE, which silently drops every foreign
 * key; a delete-cascade test could pass against code that would violate
 * constraints in production.) Statements run with foreign_key_checks off so
 * their order in the file never matters; the constraints are fully enforced
 * once the tables exist.
 */
final class TestDatabase
{
    private static ?string $name = null;

    public static function setUp(): bool
    {
        $source = (string) Config::get('database');
        $test_db = $source . '_test';

        if (!self::isSafeIdentifier($source) || !self::isSafeIdentifier($test_db) || $test_db === $source) {
            fwrite(STDERR, "Refusing to set up a test database - unsafe or ambiguous database name ({$source}).\n");

            return false;
        }

        try {
            $root = mysqli_connect('localhost', 'root', '');
        } catch (\mysqli_sql_exception $exception) {
            fwrite(STDERR, "Could not connect to the database as root over the local socket - is this running as root, and does the DB server have unix_socket auth configured for root? ({$exception -> getMessage()})\n");

            return false;
        }

        try {
            mysqli_query($root, 'DROP DATABASE IF EXISTS `' . $test_db . '`');
            mysqli_query($root, 'CREATE DATABASE `' . $test_db . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            mysqli_select_db($root, $test_db);

            // The installer's own parse, so tests can never drift from what a
            // fresh install would create. Only the CREATEs replay here - the
            // ALTER migrations further down schema.sql are the upgrade path
            // for already-installed databases, and a fresh one IS the target
            // they upgrade to.
            //
            // Foreign-key checks off during creation only, so the file's
            // ordering never matters; the constraints enforce fully once the
            // tables exist.
            mysqli_query($root, 'SET FOREIGN_KEY_CHECKS = 0');

            foreach (SchemaInstaller::createTableStatements() as $create_statement) {
                mysqli_query($root, $create_statement);
            }

            mysqli_query($root, 'SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'Failed to build the test database: ' . $exception -> getMessage() . "\n");
            mysqli_query($root, 'DROP DATABASE IF EXISTS `' . $test_db . '`');
            mysqli_close($root);

            return false;
        }

        mysqli_close($root);

        self::$name = $test_db;

        // Points DB::connection() - the app's own singleton, used by every
        // model class exactly as in production - at the throwaway database
        // via the same root/socket path, rather than teaching it a second
        // connection mode just for tests.
        putenv('DB_HOST=localhost');
        putenv('DB_USERNAME=root');
        putenv('DB_PASSWORD=');
        putenv('DB_DATABASE=' . $test_db);
        Config::reload();

        return true;
    }

    public static function tearDown(): void
    {
        if (self::$name === null) {
            return;
        }

        $name = self::$name;
        self::$name = null;

        try {
            $root = mysqli_connect('localhost', 'root', '');
            mysqli_query($root, 'DROP DATABASE IF EXISTS `' . $name . '`');
            mysqli_close($root);
        } catch (\Throwable $exception) {
            fwrite(STDERR, 'Could not drop the test database `' . $name . '`: ' . $exception -> getMessage() . "\n");
        }
    }

    private static function isSafeIdentifier(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
    }
}
