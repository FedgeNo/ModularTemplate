<?php

declare(strict_types=1);

/**
 * A converted class says only what the locale says.
 *
 * The test that a class is finished is not "no sentences left in it" but no
 * words at all - a label on a button is as untranslatable as a paragraph if it
 * is written in the class. That is easy to get almost right and hard to see:
 * the page reads correctly in English either way, and the miss only shows when
 * somebody loads it in another language.
 *
 * So each class here is rendered under a locale where every string it owns has
 * been replaced by a marker, and the English it was written with must not
 * survive that. Anything still in the output came from the class.
 *
 * A class joins SUBJECTS when it is converted. That list is the checklist: a
 * class absent from it is a class nobody has done yet, and a class in it can
 * never quietly regress.
 */
class NoEnglishInClassesTest extends TestCase
{
    /**
     * How to build one of each converted class, since they take different
     * things and a renderer needs a real object.
     *
     * @return array<string, callable(): HTMLObject>
     */
    private static function subjects(): array
    {
        $subjects = [];

        // A file per area of the site, so converting the settings forms and
        // converting the account ones are not two edits to one list.
        foreach ((array) glob(__DIR__ . '/locale-subjects/*.php') as $file) {
            $part = require (string) $file;

            if (is_array($part)) {
                $subjects = array_merge($subjects, $part);
            }
        }

        return $subjects;
    }

    /**
     * Every string in a class's entry, however deeply the entry nests.
     *
     * @param array<string, mixed> $entry
     * @return string[]
     */
    private static function leaves(array $entry): array
    {
        $found = [];

        array_walk_recursive($entry, static function ($value) use (&$found): void {
            if (is_string($value) && trim($value) !== '') {
                $found[] = trim($value);
            }
        });

        return $found;
    }

    /**
     * The same entry with every string replaced by something no language would
     * produce, so anything recognisable left in the output is the class's own.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function markered(array $entry): array
    {
        foreach ($entry as $key => $value) {
            $entry[$key] = is_array($value) ? self::markered($value) : 'ZZMARKERZZ';
        }

        return $entry;
    }

    /**
     * Everything a person reads, which is more than the text: the name on a
     * glyph-only button lives in aria-label and a picture's description in alt,
     * and textContent cannot see either - so a class whose only words are in an
     * attribute could never be checked here, and could put its English back
     * without failing anything.
     *
     * These four and not the whole markup. A class name is words too, and
     * PascalCase means they are the same words - checking the serialized
     * element instead fails SetupForm for saying "Site" in a CSS class.
     */
    private static function render(HTMLObject $object): string
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $object -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        $readable = [(string) $element -> textContent];
        $attributes = (new \DOMXPath(HTMLObject::currentDocument()))
            -> query('//@aria-label | //@title | //@alt | //@placeholder');

        foreach ($attributes as $attribute) {
            $readable[] = (string) $attribute -> nodeValue;
        }

        return implode("\n", $readable);
    }

    public function testAConvertedClassContributesNoEnglishOfItsOwn(): void
    {
        $tables = new \ReflectionProperty(Strings::class, 'tables');
        $tables -> setAccessible(true);
        $locale = new \ReflectionProperty(Strings::class, 'locale');
        $locale -> setAccessible(true);

        foreach (self::subjects() as $class => $make) {
            Strings::useLocale(Strings::SOURCE_LOCALE);
            $english = self::leaves(Strings::for($class));

            $this -> assertTrue($english !== [], $class . ' has no words in the locale file at all');

            // A locale that translates this class and nothing else, into
            // something no sentence contains.
            $original = $tables -> getValue();
            $tables -> setValue(null, array_merge($original, ['zz' => [$class => self::markered(Strings::for($class))]]));
            $locale -> setValue(null, 'zz');

            try {
                $rendered = self::render($make());
            } finally {
                $tables -> setValue(null, $original);
                $locale -> setValue(null, null);
            }

            foreach ($english as $phrase) {
                // Short fragments are punctuation and spacing rather than
                // words - " to ", ".", "@" - and would false-positive on any
                // text at all.
                if (mb_strlen($phrase) < 4) {
                    continue;
                }

                $this -> assertFalse(
                    str_contains($rendered, $phrase),
                    $class . ' still says "' . $phrase . '" itself - it must come from the locale'
                );
            }

            $this -> assertTrue(
                str_contains($rendered, 'ZZMARKERZZ'),
                $class . ' rendered none of its locale strings, so this proved nothing'
            );
        }
    }
}
