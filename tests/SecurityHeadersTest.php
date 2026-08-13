<?php

declare(strict_types=1);

class SecurityHeadersTest extends TestCase
{
    public function testNonceGeneratesABase64String()
    {
        $nonce = SecurityHeaders::nonce();
        // base64 of 16 random bytes → 24 characters
        $this -> assertSame(24, strlen($nonce));
    }

    public function testNonceReturnsTheSameValueWithinOneRequest()
    {
        $first  = SecurityHeaders::nonce();
        $second = SecurityHeaders::nonce();
        $this -> assertSame($first, $second);
    }

    public function testNonceContainsOnlyBase64Characters()
    {
        $nonce = SecurityHeaders::nonce();
        $this -> assertTrue((bool) preg_match('/^[A-Za-z0-9+\/=]+$/', $nonce));
    }

    public function testNonceIsNotEmpty()
    {
        $this -> assertTrue(strlen(SecurityHeaders::nonce()) > 0);
    }
}
