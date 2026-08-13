<?php

declare(strict_types=1);

class Select extends HTMLObject
{
    public string $tagName = 'select';
    public ?string $name = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> name !== null) {
            $this -> attributes['name'] = $this -> name;
        }

        return parent::toDOM();
    }
}
