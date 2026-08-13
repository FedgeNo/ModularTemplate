<?php

declare(strict_types=1);

/**
 * Returns a long page to its top. Sits out of the way at the bottom of the
 * viewport, and main.js only reveals it once there's enough scrolled past to
 * be worth the trip back.
 */
class ScrollToTopButton extends ButtonButton
{
    public function toDOM(): \DOMElement
    {
        $label = (string) (Strings::for(self::class)['label'] ?? '');
        $this -> attributes['aria-label'] = $label;
        $this -> contents[] = $label;

        return parent::toDOM();
    }
}
