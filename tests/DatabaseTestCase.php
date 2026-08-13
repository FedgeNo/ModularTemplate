<?php

declare(strict_types=1);

/**
 * Base for a test that needs real rows in the database - extend this instead
 * of TestCase. bin/run-tests.php only runs these when it's itself running as
 * root (see TestDatabase): building/dropping the throwaway database needs
 * the DB server's root account, so an unprivileged run skips them outright
 * rather than failing.
 *
 * Every DB test in a run shares the one seeded database TestDatabase builds
 * once and drops once - no per-test isolation - so tests that read data
 * written by an earlier test in the same run must create their own users/
 * messages/etc. rather than assuming a clean table.
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * A real bcrypt hash of $password, at the cheapest cost bcrypt has.
     *
     * password_hash() at PASSWORD_DEFAULT is deliberately slow - 435ms on this
     * box - and a fixture creates a user for nearly every DB test. That was a
     * third of the suite's runtime spent making passwords nothing will ever be
     * checked against.
     *
     * Still bcrypt and still verifiable, so nothing about the software is
     * being stood in for: password_verify() accepts these exactly as it
     * accepts a real one. Only the work factor is different, and the work
     * factor is the part that exists to slow an attacker down rather than to
     * make the hash correct.
     */
    protected static function cheapHash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    protected static function createUser(): int
    {
        $unique = bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`)
    VALUES (?, ?, ?)
', 'sss', 'test-' . $unique, 'test-' . $unique . '@example.test', self::cheapHash($unique));

        return (int) mysqli_insert_id(DB::connection());
    }

    protected static function createMessage(int $sender_id, int $recipient_id): int
    {
        DB::run('
INSERT INTO `Messages` (`senderId`, `recipientId`, `body`)
    VALUES (?, ?, ?)
', 'iis', $sender_id, $recipient_id, 'test message ' . bin2hex(random_bytes(4)));

        return (int) mysqli_insert_id(DB::connection());
    }
}
