<?php

declare(strict_types=1);

/**
 * An installed site with no usable configuration used to answer every request
 * with a redirect to the placeholder domain baked into config.php - silently,
 * and with the cause invisible from outside. It has to say what is wrong
 * instead, and say which of the two things it is.
 */
class ConfigurationErrorTest extends TestCase
{
    /**
     * Env decides "unreadable" once and caches it, so a test that wants the
     * other answer has to set it - and put it back, since every test in a run
     * shares the process.
     */
    private function withUnreadableEnv(bool $unreadable, callable $body): void
    {
        $loaded = new \ReflectionProperty(Env::class, 'loaded');
        $flag = new \ReflectionProperty(Env::class, 'unreadable');

        $was_loaded = $loaded -> getValue();
        $was_unreadable = $flag -> getValue();

        $loaded -> setValue(null, true);
        $flag -> setValue(null, $unreadable);

        try {
            $body();
        } finally {
            $loaded -> setValue(null, $was_loaded);
            $flag -> setValue(null, $was_unreadable);
        }
    }

    public function testAnUnreadableConfigurationSaysSoRatherThanNothing(): void
    {
        $this -> withUnreadableEnv(true, function (): void {
            $reason = ConfigurationError::reason();

            $this -> assertNotNull($reason);
            $this -> assertTrue(str_contains((string) $reason, 'cannot read it'));
        });
    }

    public function testAConfiguredSiteReportsNoProblem(): void
    {
        // The one test here that needs a real site address to find - the other
        // two make their own conditions up.
        $this -> requireInstallation();

        $this -> withUnreadableEnv(false, function (): void {
            $this -> assertNull(ConfigurationError::reason());
        });
    }

    public function testThePlaceholderSiteAddressIsGone(): void
    {
        // The redirect target that caused this: a real domain belonging to
        // somebody else, handed out by default to any site missing SITE_URL.
        $config = require __DIR__ . '/../src/config.php';

        $this -> assertFalse(str_contains((string) $config['siteURL'], 'example.com'));
    }
}
