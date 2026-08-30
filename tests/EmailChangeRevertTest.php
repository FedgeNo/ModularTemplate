<?php

declare(strict_types=1);

class EmailChangeRevertTest extends DatabaseTestCase
{
    public function testConsumingRestoresTheEmailBumpsSessionsAndClearsPendingTokens(): void
    {
        $user_id = self::createUser();
        $previous_email = (string) DB::row('SELECT `email` FROM `Users` WHERE `userId` = ?', \stdClass::class, 'i', $user_id) -> email;
        $session_before = User::bumpSessionVersion($user_id);
        DB::run('UPDATE `Users` SET `email` = ?, `verified` = 0 WHERE `userId` = ?', 'si', 'changed@example.test', $user_id);

        EmailVerification::create($user_id);
        $token = EmailChangeRevert::create($user_id, $previous_email);

        $this -> assertTrue(EmailChangeRevert::consume($token));

        $user = DB::row('SELECT `email`, `verified`, `sessionVersion` FROM `Users` WHERE `userId` = ?', \stdClass::class, 'i', $user_id);
        $this -> assertSame($previous_email, $user -> email);
        $this -> assertSame(1, (int) $user -> verified);
        $this -> assertTrue((int) $user -> sessionVersion > $session_before);
        $this -> assertNull(DB::row('SELECT 1 FROM `EmailVerifications` WHERE `userId` = ?', \stdClass::class, 'i', $user_id));
        $this -> assertNull(DB::row('SELECT 1 FROM `EmailChangeReverts` WHERE `userId` = ?', \stdClass::class, 'i', $user_id));
    }

    public function testTheSameTokenCannotBeConsumedTwice(): void
    {
        $user_id = self::createUser();
        $previous_email = (string) DB::row('SELECT `email` FROM `Users` WHERE `userId` = ?', \stdClass::class, 'i', $user_id) -> email;
        $token = EmailChangeRevert::create($user_id, $previous_email);

        $this -> assertTrue(EmailChangeRevert::consume($token));
        $this -> assertFalse(EmailChangeRevert::consume($token));
    }
}
