<?php

declare(strict_types=1);

/**
 * The card a search field sits in. Subclasses pair with their own SearchInput,
 * which is what the client listens on.
 */
abstract class SearchBox extends SearchLandmark
{
    public ?string $class = 'SearchBox';

    public string $placeholder = '';

    abstract protected function input(): SearchInput;

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = $this -> input();
        $this -> contents[] = new SearchClearButton();

        return parent::toDOM();
    }
}
