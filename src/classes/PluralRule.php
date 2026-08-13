<?php

declare(strict_types=1);

/**
 * Which CLDR category a count falls in, according to rules the locale file
 * carries as data.
 *
 * A language's counting is a fact about that language, so it belongs beside its
 * words rather than in code that has to be edited to add one. Adding a language
 * is adding a file - including how it counts.
 *
 * A rule is a list of cases tried in order, each naming a category and the
 * condition a count has to meet:
 *
 *   [{"category": "one", "is": [1]},
 *    {"category": "few", "mod10": [2, 3, 4], "notMod100": [12, 13, 14]},
 *    {"category": "many"}]
 *
 * A case with no condition always matches, which is how a rule ends - and a
 * language with one form is a rule of exactly that one case, which is a real
 * answer and what Japanese wants.
 */
class PluralRule
{
    /** The key a locale file keeps its rule under - not a class name, so it cannot collide with one. */
    public const KEY = '@plural';

    /**
     * @param array<int, array<string, mixed>> $rule the cases, in order
     */
    public static function categoryFor(array $rule, int $count): string
    {
        foreach ($rule as $case) {
            if (!is_array($case) || !is_string($case['category'] ?? null)) {
                continue;
            }

            if (self::matches($case, $count)) {
                return $case['category'];
            }
        }

        // What English does, which is right for a good half of the languages
        // there are - and better than nothing for a rule written wrongly.
        return $count === 1 ? 'one' : 'other';
    }

    /** @param array<string, mixed> $case */
    private static function matches(array $case, int $count): bool
    {
        foreach ($case as $test => $values) {
            if ($test === 'category') {
                continue;
            }

            if (!is_array($values)) {
                return false;
            }

            $against = match ($test) {
                'is' => $count,
                'mod10' => $count % 10,
                'mod100', 'notMod100' => $count % 100,
                default => null,
            };

            if ($against === null) {
                return false;
            }

            $found = in_array($against, $values, true);

            if ($test === 'notMod100' ? $found : !$found) {
                return false;
            }
        }

        return true;
    }
}
