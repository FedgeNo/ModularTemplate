<?php

declare(strict_types=1);

/**
 * Which language a visitor is served, and which one they are offered.
 *
 * The two are deliberately different. A browser's Accept-Language is a machine
 * setting, not an answer somebody gave this site - so it decides what to offer
 * and which language to ask in, while the site stays in English until they say
 * otherwise. A site that just followed the header would switch under a reader
 * who never asked, and would have nothing left to put in the prompt.
 *
 * The template ships English only, so these tests write a throwaway second
 * locale file for the duration - the offer machinery reads the locales/
 * directory, and an offer needs a language that is actually there to be made.
 */
class LanguageChoiceTest extends TestCase
{
    private const TEST_LOCALE_PATH = __DIR__ . '/../locales/xx.json';

    /** @param callable():void $body */
    private function withBrowserAsking(string $header, callable $body): void
    {
        $previous_header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
        $previous_session = $_SESSION['locale'] ?? null;

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $header;
        unset($_SESSION['locale']);
        Strings::useLocale(null);

        file_put_contents(self::TEST_LOCALE_PATH, (string) json_encode([
            'LanguagePrompt' => ['question' => 'Xx?', 'accept' => 'Xx', 'decline' => 'No'],
        ]));

        try {
            $body();
        } finally {
            unlink(self::TEST_LOCALE_PATH);

            if ($previous_header === null) {
                unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            } else {
                $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $previous_header;
            }

            if ($previous_session === null) {
                unset($_SESSION['locale']);
            } else {
                $_SESSION['locale'] = $previous_session;
            }

            Strings::useLocale(null);
        }
    }

    public function testABrowsersLanguageIsOfferedRatherThanApplied(): void
    {
        $this -> withBrowserAsking('xx-XX,xx;q=0.9,en;q=0.8', function (): void {
            $this -> assertSame('en', Strings::locale(), 'served in English until they choose');
            $this -> assertSame('xx', LanguagePrompt::offer(), 'and asked in their language whether they want it');
        });
    }

    public function testChoosingItIsWhatSwitchesTheSite(): void
    {
        $this -> withBrowserAsking('xx-XX,xx;q=0.9', function (): void {
            Strings::choose('xx');

            $this -> assertSame('xx', Strings::locale());
            $this -> assertNull(LanguagePrompt::offer(), 'nothing left to ask');
        });
    }

    /** Declining is an answer too, and stops the asking. */
    public function testStayingInEnglishIsAChoiceLikeAnyOther(): void
    {
        $this -> withBrowserAsking('xx-XX,xx;q=0.9', function (): void {
            Strings::choose('en');

            $this -> assertSame('en', Strings::locale());
            $this -> assertNull(LanguagePrompt::offer());
        });
    }

    /**
     * And the page says which language it ended up in. A screen reader takes
     * its pronunciation from that attribute alone, so a page in another
     * language that does not declare itself is read out in an English voice.
     */
    public function testThePageDeclaresTheLanguageItIsServedIn(): void
    {
        $this -> withBrowserAsking('xx-XX,xx;q=0.9', function (): void {
            $this -> assertTrue(str_contains((string) new HTMLDocument(), 'lang="en"'), 'before they choose');

            Strings::choose('xx');

            $this -> assertTrue(str_contains((string) new HTMLDocument(), 'lang="xx"'), 'after they choose');
        });
    }

    /** Nothing to offer somebody whose language this installation does not have. */
    public function testALanguageThisSiteDoesNotHaveIsNotOffered(): void
    {
        $this -> withBrowserAsking('is-IS,is;q=0.9', function (): void {
            $this -> assertSame('en', Strings::locale());
            $this -> assertNull(LanguagePrompt::offer());
        });
    }

    public function testABrowserThatAsksForNothingIsAskedNothing(): void
    {
        $this -> withBrowserAsking('', function (): void {
            $this -> assertSame('en', Strings::locale());
            $this -> assertNull(LanguagePrompt::offer());
        });
    }
}
