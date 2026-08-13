<?php

declare(strict_types=1);

class SelectOption extends HTMLObject
{
    public string $tagName = 'option';
    public ?string $value = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> value !== null) {
            $this -> attributes['value'] = $this -> value;
        }

        return parent::toDOM();
    }
}
