<?php

declare(strict_types=1);

class ThemeSelector extends Div
{
    public ?string $class = 'ThemeSelector';

    /**
     * The stored identifiers, in the order the menu offers them. What each is
     * called is the locale's to say - see src/locales/en/ThemeSelector.php -
     * so this is the list of themes and not a second copy of their names.
     *
     * 'light' and 'dark' keep their identifiers although their labels say
     * "Plain": the bare defaults became Sunset and Ironbow, and these are the
     * original palettes for anyone who wants them back.
     */
    protected const OPTIONS = [
        'system', 'light', 'dark', 'sepia', 'midnight', 'sunset', 'rose',
        'forest', 'ocean', 'lavender', 'gold', 'hacker', 'ironbow', 'viridis',
        'mako', 'cividis', 'ylgnbu', 'cubehelix', 'greyscale',
    ];

    /**
     * Whether the site actually offers this theme - api/update-theme.php's
     * guard. Asked of the list that builds the menu, so a theme can never be
     * offered and then refused on save.
     */
    public static function offers(string $theme): bool
    {
        return in_array($theme, self::OPTIONS, true);
    }

    public function toDOM(): \DOMElement
    {
        // Always the current user's preference - fetched here rather than
        // handed in, the same way anything needing the DB connection calls
        // DB::connection() itself.
        $selected = Auth::user() ?-> theme ?? 'system';

        $words = Strings::for(self::class);

        $label = new Label();
        $label -> for = 'theme';
        $label -> contents[] = (string) ($words['label'] ?? '');
        $this -> contents[] = $label;

        $select = new Select();
        $select -> name = 'theme';
        $select -> id = 'theme';
        $select -> class = 'ThemeSelect';

        foreach (self::OPTIONS as $value) {
            $option = new SelectOption();
            $option -> value = $value;
            $option -> contents[] = (string) ($words['themes'][$value] ?? $value);

            if ($value === $selected) {
                $option -> attributes['selected'] = 'selected';
            }

            $select -> addContent($option);
        }

        $this -> addContent($select);

        return parent::toDOM();
    }
}
