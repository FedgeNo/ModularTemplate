<?php

declare(strict_types=1);

class Textarea extends HTMLObject
{
    public string $tagName = 'textarea';
    public ?string $name = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> name !== null) {
            $this -> attributes['name'] = $this -> name;
        }

        return parent::toDOM();
    }
}
