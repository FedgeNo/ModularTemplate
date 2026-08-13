<?php

declare(strict_types=1);

class Fieldset extends HTMLObject
{
    public string $tagName = 'fieldset';

    public string $legend;

    public function __construct(string $legend)
    {
        parent::__construct();

        $this -> legend = $legend;
    }

    public function toDOM(): \DOMElement
    {
        $legend = new Legend();
        $legend -> contents[] = $this -> legend;
        array_unshift($this -> contents, $legend);

        return parent::toDOM();
    }
}
