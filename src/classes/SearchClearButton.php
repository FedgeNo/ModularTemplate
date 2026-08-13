<?php

declare(strict_types=1);

/**
 * Empties the search field it sits in. Hidden while the field is empty, so it
 * only appears once there's something to clear.
 */
class SearchClearButton extends ButtonButton
{
    public function toDOM(): \DOMElement
    {
        $this -> attributes['aria-label'] = (string) (Strings::for(self::class)['label'] ?? '');
        $this -> contents[] = '✘';

        return parent::toDOM();
    }
}
