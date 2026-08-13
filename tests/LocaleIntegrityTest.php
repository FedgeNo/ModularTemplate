<?php

declare(strict_types=1);

/**
 * A translation that renders is not the same as a translation that is right.
 *
 * NoEnglishInClassesTest proves a class holds no words of its own. Nothing
 * proved anything about the words a locale file replaces them with, and the
 * ways those go wrong are silent: a {count} spelled differently is a token
 * printed literally at somebody, and a counted phrase missing the category its
 * own language can ask for falls back to a form that reads wrong in exactly
 * the cases the extra form existed for.
 *
 * A locale is allowed to be unfinished - that is the whole point of the
 * per-piece fallback - so nothing here fails for a missing entry. It fails for
 * an entry that is present and broken.
 */
class LocaleIntegrityTest extends TestCase
{
    /**
     * The categories CLDR defines. A counted entry is recognised by having
     * only these as keys, which no sentence-of-pieces entry does - those are
     * keyed before/link/after and the like.
     *
     * @var string[]
     */
    private const CATEGORIES = ['zero', 'one', 'two', 'few', 'many', 'other'];

    /** @return array<string, mixed> */
    private static function table(string $locale): array
    {
        $method = new \ReflectionMethod(Strings::class, 'table');
        $method -> setAccessible(true);

        return (array) $method -> invoke(null, $locale);
    }

    /** @param array<mixed> $entry */
    private static function isCounted(array $entry): bool
    {
        if ($entry === []) {
            return false;
        }

        foreach (array_keys($entry) as $key) {
            if (!in_array($key, self::CATEGORIES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Which categories a locale's own rule can actually produce, found by
     * asking it rather than by looking the language up: the rule in the file is
     * the only thing that decides, so it is the only thing worth checking
     * against. The range covers every shape CLDR rules turn on - the teens and
     * the hundreds are where Slavic and Arabic rules change their minds.
     *
     * @return string[]
     */
    private static function categoriesUsedBy(string $locale): array
    {
        $rule = self::table($locale)[Strings::PLURAL_RULE] ?? [];

        if (!is_array($rule) || $rule === []) {
            return ['one', 'other'];
        }

        $found = [];

        foreach ([...range(0, 130), 200, 201, 1000, 1002, 1005, 1011, 1024] as $count) {
            $found[PluralRule::categoryFor($rule, $count)] = true;
        }

        return array_keys($found);
    }

    /** The {tokens} in a string, which name a value the code substitutes. @return string[] */
    private static function tokensIn(string $text): array
    {
        preg_match_all('/\{[a-zA-Z]+\}/', $text, $matches);

        $tokens = array_unique($matches[0]);
        sort($tokens);

        return $tokens;
    }

    /**
     * Every leaf in a table, keyed by the path that reaches it.
     *
     * @param array<string, mixed> $table
     * @return array<string, string>
     */
    private static function leaves(array $table, string $prefix = ''): array
    {
        $found = [];

        foreach ($table as $key => $value) {
            if ($key === Strings::PLURAL_RULE) {
                continue;
            }

            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $found += self::leaves($value, $path);
            } elseif (is_string($value)) {
                $found[$path] = $value;
            }
        }

        return $found;
    }

    /**
     * A locale that has written a counted phrase has written every form its own
     * rule can ask for. Missing one is not a fallback that reads a little
     * wrong - it is the language's own grammar going unsaid for the numbers it
     * was added for.
     */
    public function testACountedPhraseCarriesEveryFormItsLanguageAsksFor(): void
    {
        foreach (Strings::available() as $locale) {
            $categories = self::categoriesUsedBy($locale);

            foreach (self::table($locale) as $class => $entry) {
                if ($class === Strings::PLURAL_RULE || !is_array($entry)) {
                    continue;
                }

                foreach ($entry as $key => $value) {
                    if (!is_array($value) || !self::isCounted($value)) {
                        continue;
                    }

                    foreach ($categories as $category) {
                        $this -> assertTrue(
                            isset($value[$category]),
                            $locale . ' ' . $class . '.' . $key . ' has no "' . $category
                                . '" form, which its own plural rule asks for'
                        );
                    }
                }
            }
        }
    }

    /**
     * A token names a value the code substitutes, so a translation may only use
     * the ones the code has for that entry. Respell {count} and the reader is
     * shown the brace text itself, while English reads perfectly.
     *
     * Which token belongs where is the language's business, not English's:
     * Portuguese counts 0 and 1 both as "one", so its singular has to say
     * {count} where the English - whose "one" is only ever 1 - hardcodes the
     * numeral. So the rule is that a translation invents no token, not that it
     * carries the same ones.
     */
    public function testATranslationInventsNoTokenTheCodeCannotFill(): void
    {
        $source = self::table(Strings::SOURCE_LOCALE);

        foreach (Strings::available() as $locale) {
            if ($locale === Strings::SOURCE_LOCALE) {
                continue;
            }

            foreach (self::table($locale) as $class => $entry) {
                if ($class === Strings::PLURAL_RULE || !is_array($entry) || !isset($source[$class])) {
                    continue;
                }

                // Every token the code substitutes anywhere in this class's
                // words - the whole entry, since a counted phrase's forms share
                // one substitution.
                $known = [];

                foreach (self::leaves((array) $source[$class]) as $english) {
                    $known = array_merge($known, self::tokensIn($english));
                }

                foreach (self::leaves($entry) as $path => $text) {
                    foreach (self::tokensIn($text) as $token) {
                        $this -> assertTrue(
                            in_array($token, $known, true),
                            $locale . ' ' . $class . '.' . $path . ' uses ' . $token
                                . ', which the code never puts anything into'
                        );
                    }
                }
            }
        }
    }

    /**
     * A locale file that is a list where English is a sentence renders nothing
     * usable.
     *
     * A counted phrase is the exception, and the reason this cannot simply
     * demand the same paths: how many forms a phrase has is the language's
     * answer, not English's. Polish writes a "few" where English has only "one"
     * and "other", and that extra form is the whole point of the mechanism - so
     * a category the locale's own rule can produce is not a stray key.
     */
    public function testATranslationKeepsTheShapeOfWhatItReplaces(): void
    {
        $source = self::leaves(self::table(Strings::SOURCE_LOCALE));

        foreach (Strings::available() as $locale) {
            if ($locale === Strings::SOURCE_LOCALE) {
                continue;
            }

            $categories = self::categoriesUsedBy($locale);

            foreach (array_keys(self::leaves(self::table($locale))) as $path) {
                if (isset($source[$path])) {
                    continue;
                }

                // The path with its last segment removed, and that segment.
                $cut = strrpos($path, '.');
                $entry = $cut === false ? '' : substr($path, 0, $cut);
                $form = $cut === false ? $path : substr($path, $cut + 1);

                $this -> assertTrue(
                    in_array($form, $categories, true) && self::isCountedInSource($entry),
                    $locale . ' says "' . $path . '", which English has no such string for'
                );
            }
        }
    }

    /** Whether English writes this path as a set of phrasings keyed by category. */
    private static function isCountedInSource(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $entry = self::table(Strings::SOURCE_LOCALE);

        foreach (explode('.', $path) as $step) {
            if (!is_array($entry) || !array_key_exists($step, $entry)) {
                return false;
            }

            $entry = $entry[$step];
        }

        return is_array($entry) && self::isCounted($entry);
    }
}
