<?php

declare(strict_types=1);

/**
 * Base class for a test suite - one class per thing under test (e.g.
 * LinkifyTest, URLTest), same "one class per concept" convention as the rest
 * of the app. A test case is any public method whose name starts with
 * 'test'; bin/run-tests.php discovers and runs them by reflection, same
 * spirit as the app's own spl_autoload_register - no external test runner,
 * no Composer, matching this codebase's zero-dependency, hand-rolled-over-
 * a-library instinct everywhere else (the SMTP client, the WebSocket
 * server, the HTML renderer).
 *
 * An assertion failure throws AssertionFailedException rather than
 * returning a bool, so a test method can just read top-to-bottom - the
 * runner catches it per-method and keeps going, same as it does for a
 * genuine error.
 */
abstract class TestCase
{
    private function fail(string $message): never
    {
        throw new AssertionFailedException($message);
    }

    protected function assertTrue(bool $condition, string $message = 'Expected true, got false'): void
    {
        if ($condition !== true) {
            $this -> fail($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'Expected false, got true'): void
    {
        if ($condition !== false) {
            $this -> fail($message);
        }
    }

    protected function assertNull(mixed $value, string $message = 'Expected null'): void
    {
        if ($value !== null) {
            $this -> fail($message . ' - got ' . self::describe($value));
        }
    }

    protected function assertNotNull(mixed $value, string $message = 'Expected a non-null value'): void
    {
        if ($value === null) {
            $this -> fail($message);
        }
    }

    /**
     * Strict (===) comparison - this codebase leans on strict typing
     * throughout (declare(strict_types=1) everywhere), so the test harness
     * does too rather than PHPUnit-style loose assertEquals.
     */
    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this -> fail(($message !== '' ? $message . ' - ' : '') . 'expected ' . self::describe($expected) . ', got ' . self::describe($actual));
        }
    }

    protected function assertCount(int $expected, array $array, string $message = ''): void
    {
        $actual = count($array);

        if ($expected !== $actual) {
            $this -> fail(($message !== '' ? $message . ' - ' : '') . 'expected ' . $expected . ' item(s), got ' . $actual);
        }
    }

    /**
     * Stands this test down where the installation it reads is not there.
     *
     * A few tests assert against the real configuration rather than a fixture
     * - the site's own address, and the settings table that address leads to.
     * An unprivileged run cannot read .env at all, so those were failing on
     * something that is not about the software, and a suite with four
     * permanent red marks in it stops being read. They run under sudo, like
     * the database-backed ones, and stand down otherwise.
     */
    protected function requireInstallation(): void
    {
        if (Env::unreadable() || (string) Env::get('SITE_URL', '') === '') {
            throw new TestSkippedException('needs the installation\'s own configuration - re-run with sudo');
        }
    }

    private static function describe(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . $value . "'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '(unencodable)';
        }

        return (string) $value;
    }
}

class AssertionFailedException extends \Exception
{
}

/**
 * Thrown by a test that cannot run here - not a pass and not a failure. The
 * runner reports these apart from both, so what was skipped stays visible
 * rather than quietly reading as success.
 */
class TestSkippedException extends \Exception
{
}
