<?php

declare(strict_types=1);

/**
 * The words the software says, in whichever language it is saying them.
 *
 * One JSON file per locale under locales/ - the same files the browser
 * fetches, read here rather than copied for it.
 *
 * Everything a person reads comes from here, including the label on a button.
 * A class keeps only the shape of a sentence - which piece is a link, where it
 * points - so "is this class translated" has an answer anybody can check: it
 * contains no English.
 *
 * Missing keys fall back to English rather than to a blank or a key name. That
 * is what makes this possible to adopt at all: over a thousand strings cannot
 * be converted in one change, and every intermediate state has to be a site
 * somebody can use.
 */
class Strings
{
    /** The locale every other one falls back to, and the one the code is written in. */
    public const SOURCE_LOCALE = 'en';

    /**
     * One JSON file per language, in locales/ - the same files the browser
     * fetches, read here rather than copied for it.
     */
    private const DIRECTORY = __DIR__ . '/../../locales';

    private static ?string $locale = null;

    /** @var array<string, array<string, mixed>> loaded tables by locale */
    private static array $tables = [];

    /**
     * The locales this installation has words for, source first.
     *
     * Read off the directory rather than listed anywhere: adding a translation
     * is adding a file, and a list would be a second place to remember.
     *
     * @return string[]
     */
    public static function available(): array
    {
        $found = [];

        foreach ((array) glob(self::DIRECTORY . '/*.json') as $path) {
            $found[] = basename((string) $path, '.json');
        }

        sort($found);

        return $found;
    }

    /**
     * Which language to say it in: what this reader asked for, and English
     * until they ask.
     *
     * What the browser asks for is an offer rather than an answer - it decides
     * which language to put the question in (see LanguagePrompt), not which one
     * to serve. A site that simply followed the header would switch under
     * somebody who never said they wanted it, on a machine whose language is
     * not necessarily theirs, and would have nothing left to ask them.
     */
    public static function locale(): string
    {
        if (self::$locale !== null) {
            return self::$locale;
        }

        $chosen = self::chosen();

        if ($chosen !== null && in_array($chosen, self::available(), true)) {
            return self::$locale = $chosen;
        }

        return self::$locale = self::SOURCE_LOCALE;
    }

    /**
     * The language this reader has asked for, or null if they never have.
     *
     * A member's answer lives on their row, so it follows them to any browser
     * they sign in from. Somebody signed out has only this session to keep it
     * in, which is as long as they are here for.
     */
    private static function chosen(): ?string
    {
        $stored = Auth::check() ? Auth::user() ?-> locale : null;

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $session = $_SESSION['locale'] ?? null;

        return is_string($session) && $session !== '' ? $session : null;
    }

    /**
     * Remembers what a reader asked for: on their row where there is one, and
     * in their session either way so the page they are on already changes.
     */
    public static function choose(string $locale): bool
    {
        if (!in_array($locale, self::available(), true)) {
            return false;
        }

        $_SESSION['locale'] = $locale;
        self::$locale = $locale;

        if (Auth::check()) {
            DB::run('
UPDATE `Users`
    SET `locale` = ?
    WHERE `userId` = ?
', 'si', $locale, Auth::id());
        }

        return true;
    }

    /** Whether this reader has said which language they want. */
    public static function hasChosen(): bool
    {
        return self::chosen() !== null;
    }

    /**
     * The language this reader's browser asks for and this site has, or null
     * where it asks for nothing this site speaks.
     *
     * Their own preference, not the site's answer: locale() falls back to
     * English, and a fallback is not something to offer somebody as a choice.
     */
    public static function preferred(): ?string
    {
        $available = self::available();

        foreach (self::preferredLanguages() as $language) {
            $base = strtolower(explode('-', $language)[0]);

            if (in_array($base, $available, true)) {
                return $base;
            }
        }

        return null;
    }

    /**
     * What one class says in a named language rather than in the current one -
     * for asking somebody, in the language they read, whether they would like
     * it. Falls back to English per key, the same as for().
     *
     * @return array<string, mixed>
     */
    public static function forLocale(string $class, string $locale): array
    {
        return array_replace_recursive(
            self::table(self::SOURCE_LOCALE)[$class] ?? [],
            self::table($locale)[$class] ?? []
        );
    }

    /** Set the locale directly, for the times nothing is asking a browser. */
    public static function useLocale(?string $locale): void
    {
        self::$locale = $locale !== null && in_array($locale, self::available(), true) ? $locale : null;
    }

    /** Where a locale file keeps its counting rule - see PluralRule. */
    public const PLURAL_RULE = PluralRule::KEY;

    /**
     * One of a set of phrasings, chosen by how many of the thing there are.
     *
     * English has two forms and most of the code was written assuming that -
     * `$n === 1 ? 'vote' : 'votes'`. Polish has three and Arabic six, and no
     * amount of care with a ternary in a class will produce them; the choosing
     * has to belong to the language.
     *
     * So a counted string is a set of phrasings keyed by CLDR category, and
     * each locale file says which category a number falls in. A locale that
     * gives only "other" is a language with one form, which is a real answer
     * and what Japanese wants.
     *
     * The count is not substituted here: the phrasing says where the number
     * goes, or leaves it out, which is a thing languages differ about too.
     */
    public static function plural(string $class, string $key, int $count): string
    {
        $forms = self::for($class)[$key] ?? [];

        if (!is_array($forms) || $forms === []) {
            return '';
        }

        $category = self::category($count);

        // Falling back to "other" rather than to nothing: a locale that has
        // not written its "few" yet should read a little wrong, not vanish.
        $chosen = $forms[$category] ?? $forms['other'] ?? reset($forms);

        return is_string($chosen) ? $chosen : '';
    }

    /** Which CLDR category a count falls in, by the current locale's own rule. */
    private static function category(int $count): string
    {
        $rule = self::table(self::locale())[self::PLURAL_RULE]
            ?? self::table(self::SOURCE_LOCALE)[self::PLURAL_RULE]
            ?? [];

        return PluralRule::categoryFor(is_array($rule) ? $rule : [], $count);
    }

    /**
     * What one class says, in the current locale, with anything untranslated
     * falling back to the English it was written in.
     *
     * @return array<string, mixed>
     */
    public static function for(string $class): array
    {
        $words = self::table(self::locale())[$class] ?? [];
        $source = self::table(self::SOURCE_LOCALE)[$class] ?? [];

        // Recursive, so a locale that translates one phrasing of a sentence
        // and not another keeps English for the one it missed rather than
        // losing it along with the rest of the entry.
        return array_replace_recursive($source, $words);
    }

    /**
     * The whole table, for handing to the browser - the locales/*.js modules
     * the client twins read are this, written out.
     *
     * @return array<string, mixed>
     */
    public static function all(?string $locale = null): array
    {
        $locale ??= self::locale();

        return array_replace_recursive(self::table(self::SOURCE_LOCALE), self::table($locale));
    }

    /** @return array<string, mixed> */
    private static function table(string $locale): array
    {
        if (isset(self::$tables[$locale])) {
            return self::$tables[$locale];
        }

        // Checked against the directory listing rather than pattern-matched:
        // the only safe answer to "which file" is one of the files that is
        // actually there.
        if (!in_array($locale, self::available(), true)) {
            return self::$tables[$locale] = [];
        }

        $decoded = json_decode((string) @file_get_contents(self::DIRECTORY . '/' . $locale . '.json'), true);

        return self::$tables[$locale] = is_array($decoded) ? $decoded : [];
    }

    /**
     * The languages the browser asked for, best first.
     *
     * @return string[]
     */
    private static function preferredLanguages(): array
    {
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');

        if (trim($header) === '') {
            return [];
        }

        $languages = [];

        foreach (explode(',', $header) as $part) {
            $pieces = explode(';q=', trim($part));
            $tag = trim($pieces[0]);

            if ($tag === '' || $tag === '*') {
                continue;
            }

            $languages[$tag] = (float) ($pieces[1] ?? 1.0);
        }

        arsort($languages);

        return array_keys($languages);
    }
}
