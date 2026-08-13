<?php

declare(strict_types=1);

/**
 * Saying it in whichever language the reader asked for.
 *
 * The two claims worth holding are that a class contains no English of its own
 * and that a locale which has not been finished still renders a usable page.
 * The second is what makes a large string table adoptable at all: without it
 * every intermediate state is a site with blanks in it, and the conversion
 * has to land as one enormous change or not at all.
 *
 * The runner calls test methods and nothing else, so each of these puts the
 * locale back itself - one left set would follow every later test in the run.
 */
class StringsTest extends TestCase
{
    private static function render(HTMLObject $object): string
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $object -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return (string) HTMLObject::currentDocument() -> saveHTML($element);
    }

    /** Installs a throwaway locale table, so no test needs a real second locale file. */
    private static function inject(string $locale, array $table): void
    {
        $tables = new \ReflectionProperty(Strings::class, 'tables');
        $tables -> setAccessible(true);
        $locale_property = new \ReflectionProperty(Strings::class, 'locale');
        $locale_property -> setAccessible(true);

        $tables -> setValue(null, [$locale => $table]);
        $locale_property -> setValue(null, $locale);
    }

    private static function reset(): void
    {
        $tables = new \ReflectionProperty(Strings::class, 'tables');
        $tables -> setAccessible(true);
        $tables -> setValue(null, []);
        Strings::useLocale(null);
    }

    public function testTheSourceLanguageReadsAsItAlwaysDid(): void
    {
        Strings::useLocale('en');

        try {
            $this -> assertSame('Log Out', trim(strip_tags(self::render(new LogoutForm()))));
        } finally {
            self::reset();
        }
    }

    /** The words move with the language; the class knows none of them. */
    public function testAnotherLanguageMovesTheWords(): void
    {
        self::inject('xx', ['LogoutForm' => ['submit' => 'Abmelden']]);

        try {
            $text = trim(strip_tags(self::render(new LogoutForm())));

            $this -> assertSame('Abmelden', $text);
            $this -> assertFalse(str_contains($text, 'Log Out'), 'the class contributes no English of its own');
        } finally {
            self::reset();
        }
    }

    /**
     * A locale that has not been finished falls back piece by piece, not entry
     * by entry - so translating one string of a class does not blank the
     * others, and one class can be converted per commit.
     */
    public function testWhatALocaleHasNotTranslatedIsStillSaidInEnglish(): void
    {
        self::inject('xx', ['MainNavigation' => ['login' => 'Ingresar']]);

        try {
            $words = Strings::for('MainNavigation');

            $this -> assertSame('Ingresar', $words['login'], 'the translated piece is used');
            $this -> assertSame('Sign Up', $words['signup'], 'and the piece nobody translated is still said');
        } finally {
            self::reset();
        }
    }

    public function testACountChoosesThePhrasing(): void
    {
        self::inject('xx', [
            Strings::PLURAL_RULE => [
                ['category' => 'one', 'is' => [1]],
                ['category' => 'other'],
            ],
            'Widget' => ['votes' => [
                'one' => '1 vote',
                'other' => '{count} votes',
            ]],
        ]);

        try {
            $this -> assertSame('1 vote', Strings::plural('Widget', 'votes', 1));
            $this -> assertSame('{count} votes', Strings::plural('Widget', 'votes', 0));
            $this -> assertSame('{count} votes', Strings::plural('Widget', 'votes', 7));
        } finally {
            self::reset();
        }
    }

    /**
     * The case English cannot demonstrate and the reason none of this is a
     * ternary in a class: Polish has three forms, and 2 takes a different one
     * from 5.
     */
    public function testALanguageWithThreeFormsGetsThreeForms(): void
    {
        self::inject('pl', [
            // As a locale file carries it: cases tried in order, each naming a
            // category and what a count has to satisfy to take it.
            Strings::PLURAL_RULE => [
                ['category' => 'one', 'is' => [1]],
                ['category' => 'few', 'mod10' => [2, 3, 4], 'notMod100' => [12, 13, 14]],
                ['category' => 'many'],
            ],
            'Widget' => ['votes' => [
                'one' => '1 głos',
                'few' => '{count} głosy',
                'many' => '{count} głosów',
            ]],
        ]);

        try {
            $this -> assertSame('1 głos', Strings::plural('Widget', 'votes', 1));
            $this -> assertSame('{count} głosy', Strings::plural('Widget', 'votes', 2));
            $this -> assertSame('{count} głosów', Strings::plural('Widget', 'votes', 5));
            $this -> assertSame('{count} głosów', Strings::plural('Widget', 'votes', 12));
            $this -> assertSame('{count} głosy', Strings::plural('Widget', 'votes', 22));
        } finally {
            self::reset();
        }
    }

    /** A phrasing a locale has not written yet reads a little wrong rather than vanishing. */
    public function testAMissingFormFallsBackRatherThanDisappearing(): void
    {
        self::inject('xx', [
            Strings::PLURAL_RULE => [
                ['category' => 'few', 'is' => [3]],
                ['category' => 'other'],
            ],
            'Widget' => ['votes' => ['other' => '{count} stemmer']],
        ]);

        try {
            $this -> assertSame('{count} stemmer', Strings::plural('Widget', 'votes', 3));
        } finally {
            self::reset();
        }
    }

    /** English is the source, so it is always one of the languages on offer. */
    public function testTheSourceLanguageIsAlwaysAvailable(): void
    {
        $this -> assertTrue(in_array(Strings::SOURCE_LOCALE, Strings::available(), true));
    }

    /** A locale nobody has written words for is not one this can be asked to load. */
    public function testAnUnknownLocaleIsRefusedRatherThanRequired(): void
    {
        Strings::useLocale('../../etc/passwd');

        try {
            $this -> assertSame(Strings::SOURCE_LOCALE, Strings::locale());
        } finally {
            Strings::useLocale(null);
        }
    }
}
