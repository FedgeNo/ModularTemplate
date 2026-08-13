<?php

declare(strict_types=1);

/**
 * When a member was last here.
 *
 * Written from init.php on any request with somebody behind it, which is the
 * only place that knows - so the thing that matters is that it does not turn
 * every page view into a write. A person reading for an hour should cost a
 * handful, not one per click.
 */
class LastSeenTest extends DatabaseTestCase
{
    private function reload(int $user_id): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $user_id);
    }

    public function testBeingHereIsRecorded(): void
    {
        $user = $this -> reload(self::createUser());

        $this -> assertNull($user -> lastSeen, 'a new account has not been anywhere yet');

        User::seen($user);

        $this -> assertNotNull($this -> reload((int) $user -> userId) -> lastSeen);
    }

    /**
     * The mark is left alone while it is fresh. Without this every request
     * from every signed-in member is a write, for a figure nobody needs to
     * the second.
     */
    public function testAFreshMarkIsNotWrittenAgain(): void
    {
        $user = $this -> reload(self::createUser());

        User::seen($user);
        $first = $this -> reload((int) $user -> userId) -> lastSeen;

        // A minute later, still the same request-storm - nothing should move.
        DB::run('
UPDATE `Users`
    SET `lastSeen` = NOW() - INTERVAL 1 MINUTE
    WHERE `userId` = ?
', 'i', (int) $user -> userId);

        $recent = $this -> reload((int) $user -> userId);
        $before = $recent -> lastSeen;

        User::seen($recent);

        $this -> assertSame($before, $this -> reload((int) $user -> userId) -> lastSeen);
        $this -> assertNotNull($first);
    }

    /** Once it has gone stale, the next request moves it. */
    public function testAStaleMarkIsBroughtForward(): void
    {
        $user = $this -> reload(self::createUser());

        DB::run('
UPDATE `Users`
    SET `lastSeen` = NOW() - INTERVAL 1 HOUR
    WHERE `userId` = ?
', 'i', (int) $user -> userId);

        $stale = $this -> reload((int) $user -> userId);
        $before = $stale -> lastSeen;

        User::seen($stale);

        $this -> assertFalse($before === $this -> reload((int) $user -> userId) -> lastSeen);
    }

}
