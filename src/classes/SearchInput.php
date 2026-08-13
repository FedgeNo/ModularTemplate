<?php

declare(strict_types=1);

/**
 * The text field of a SearchBox. Autocomplete is off because the browser's own
 * history dropdown covers the live results as they arrive.
 */
class SearchInput extends TextInput
{
    public ?string $class = 'SearchInput';

    public string $placeholder = '';

    public function toDOM(): \DOMElement
    {
        $this -> name = 'q';
        $this -> attributes['placeholder'] = $this -> placeholder;
        $this -> attributes['autocomplete'] = 'off';

        // A placeholder is not a label - it is gone the moment anybody types,
        // and several screen readers never announce it at all. The box's own
        // placeholder is what it would have been called, so it is called that.
        $this -> attributes['aria-label'] = $this -> placeholder !== '' ? $this -> placeholder : 'Search';

        // Announces itself as a search field rather than a text box, which is
        // what a reader skipping between landmarks is looking for.
        $this -> attributes['type'] = 'search';

        return parent::toDOM();
    }
}
