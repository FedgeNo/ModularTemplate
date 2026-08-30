<?php

declare(strict_types=1);

/**
 * Which language a member reads the site in.
 *
 * Every option is written in its own language, so somebody who cannot read the
 * page they are on can still find the one they want - which is the whole
 * situation this exists for.
 */
class LanguageSelector extends Div
{
    public ?string $class = 'LanguageSelector';

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $label = new Label();
        $label -> for = 'locale';
        $label -> contents[] = (string) ($words['label'] ?? '');
        $this -> contents[] = $label;

        $select = new Select();
        $select -> name = 'locale';
        $select -> id = 'locale';
        $select -> class = 'LanguageSelect';

        $chosen = Strings::locale();

        foreach (LanguageName::all() as $locale => $name) {
            $option = new SelectOption();
            $option -> value = $locale;
            $option -> contents[] = $name;

            if ($locale === $chosen) {
                $option -> attributes['selected'] = 'selected';
            }

            $select -> addContent($option);
        }

        $this -> addContent($select);

        return parent::toDOM();
    }
}
