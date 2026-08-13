<?php

declare(strict_types=1);

/**
 * What each language calls itself.
 *
 * Not in the locale files, and deliberately: a language's own name is the same
 * string whichever language is asking. Deutsch is Deutsch on the English
 * settings page and on the Japanese one, and putting it in every locale file
 * would be nine copies of one fact - nine chances for a language to be listed
 * under a name its own speakers do not use.
 *
 * Written the way each is written by the people who speak it, in its own
 * script, so somebody who cannot read the rest of the page can still find
 * their language in the list.
 */
class LanguageName
{
    /** @var array<string, string> locale => what its speakers call it */
    private const NAMES = [
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
        'ja' => '日本語',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'pt' => 'Português',
    ];

    public static function of(string $locale): string
    {
        return self::NAMES[$locale] ?? $locale;
    }

    /**
     * Every language this installation has words for, in its own name.
     *
     * Ordered by that name rather than by locale code, since the name is what
     * somebody is reading down the list looking for.
     *
     * @return array<string, string> locale => name
     */
    public static function all(): array
    {
        $named = [];

        foreach (Strings::available() as $locale) {
            $named[$locale] = self::of($locale);
        }

        asort($named, SORT_NATURAL | SORT_FLAG_CASE);

        return $named;
    }
}
