<?php

declare(strict_types=1);

class ServerURLTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
    }

    // ---------- host ----------

    public function testHostReturnsTheHostFromSiteUrl()
    {
        // Reads the configured site address, so there has to be one.
        $this -> requireInstallation();

        $host = ServerURL::host();
        $this -> assertTrue(is_string($host));
        $this -> assertTrue(strlen($host) > 0);
        $this -> assertFalse(str_contains($host, '://'));
        $this -> assertFalse(str_contains($host, ':'));
    }

    // ---------- isHTTPS ----------

    public function testIsHttpsReturnsFalseWhenHttpsKeyIsAbsent()
    {
        $this -> assertFalse(ServerURL::isHTTPS());
    }

    public function testIsHttpsReturnsFalseWhenHttpsIsOff()
    {
        $_SERVER['HTTPS'] = 'off';
        $this -> assertFalse(ServerURL::isHTTPS());
    }

    public function testIsHttpsReturnsTrueWhenHttpsIsOn()
    {
        $_SERVER['HTTPS'] = 'on';
        $this -> assertTrue(ServerURL::isHTTPS());
    }

    // ---------- absolute ----------

    public function testAbsolutePrependsSiteUrlHost()
    {
        // Reads the configured site address, so there has to be one.
        $this -> requireInstallation();

        $url = ServerURL::absolute('/test');
        $this -> assertTrue(str_starts_with($url, 'https://'));
        $this -> assertTrue(str_ends_with($url, '/test'));
    }

    public function testAbsoluteDoesNotUseHostHeader()
    {
        $_SERVER['HTTP_HOST'] = 'evil.com';
        $url = ServerURL::absolute('/path');
        $this -> assertFalse(str_contains($url, 'evil.com'));
    }

    public function testAbsoluteIgnoresPortFromHostHeader()
    {
        $_SERVER['HTTP_HOST'] = 'real-host.com:666';
        $_SERVER['SERVER_PORT'] = '666';
        $url = ServerURL::absolute('/path');
        $this -> assertFalse(str_contains($url, 'real-host.com'));
        $this -> assertFalse(str_contains($url, ':666'));
    }
}
