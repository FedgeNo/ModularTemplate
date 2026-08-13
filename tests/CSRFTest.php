<?php

declare(strict_types=1);

class CSRFTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTokenGeneratesA64CharacterHexString()
    {
        $token = CSRF::token();
        $this -> assertSame(64, strlen($token));
        $this -> assertTrue((bool) preg_match('/^[0-9a-f]{64}$/', $token));
    }

    public function testTokenReturnsTheSameValueOnSubsequentCalls()
    {
        $first  = CSRF::token();
        $second = CSRF::token();
        $this -> assertSame($first, $second);
    }

    public function testTokenIsStoredInSession()
    {
        $token = CSRF::token();
        $this -> assertSame($token, $_SESSION['CSRFToken']);
    }

    public function testVerifyReturnsFalseForNullToken()
    {
        CSRF::token();
        $this -> assertFalse(CSRF::verify(null));
    }

    public function testVerifyReturnsFalseForEmptyString()
    {
        CSRF::token();
        $this -> assertFalse(CSRF::verify(''));
    }

    public function testVerifyReturnsFalseForWrongToken()
    {
        CSRF::token();
        $this -> assertFalse(CSRF::verify(str_repeat('0', 64)));
    }

    public function testVerifyReturnsTrueForCorrectToken()
    {
        $token = CSRF::token();
        $this -> assertTrue(CSRF::verify($token));
    }

    public function testVerifyIsTimingSafeAgainstPrefixMatches()
    {
        $token = CSRF::token();
        $prefixMatch = substr($token, 0, 32) . str_repeat('0', 32);
        $this -> assertFalse(CSRF::verify($prefixMatch));
    }

    public function testVerifyReturnsFalseWhenSessionHasNoToken()
    {
        $_SESSION = [];
        $this -> assertFalse(CSRF::verify('anything'));
    }
}
