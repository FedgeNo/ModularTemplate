<?php

declare(strict_types=1);

class URLTest extends TestCase
{
    public function testRejectsNonHTTPScheme(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('ftp://example.com/file'));
    }

    public function testRejectsMalformedURL(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('not a url at all'));
    }

    public function testAcceptsHTTPSRealHostname(): void
    {
        // A shape check only - no DNS lookup, so a real TLD under a
        // never-registered label still validates.
        $this -> assertTrue(URL::isValidHTTPURL('https://nonexistent-test-a9f3c1.com/path'));
    }

    public function testRejectsSingleLabelHost(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('http://intranet/admin'));
    }

    public function testRejectsFakeTLD(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('https://example.notarealtld/path'));
    }

    public function testRejectsLocalhostHostname(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('http://localhost/admin'));
    }

    public function testRejectsDotLocalhostSuffix(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('http://foo.localhost/admin'));
    }

    public function testRejectsDotLocalSuffix(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('http://printer.local/admin'));
    }

    public function testRejectsLiteralLoopbackIP(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('http://127.0.0.1/admin'));
    }

    public function testRejectsLiteralPrivateRangeIP(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('http://10.0.0.5/admin'));
        $this -> assertFalse(URL::isValidHTTPURL('http://192.168.1.1/admin'));
    }

    public function testRejectsBracketedIPv6Loopback(): void
    {
        $this -> assertFalse(URL::isValidHTTPURL('http://[::1]/admin'));
    }

    public function testRejectsLiteralPublicIP(): void
    {
        // No IP links at all - only a real registrable hostname is postable,
        // even a public IP is refused.
        $this -> assertFalse(URL::isValidHTTPURL('http://8.8.8.8/'));
    }
}
