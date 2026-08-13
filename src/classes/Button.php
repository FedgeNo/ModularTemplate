<?php

declare(strict_types=1);

class Button extends HTMLObject
{
    public string $tagName = 'button';
    public string $type = 'button';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['type'] = $this -> type;
        return parent::toDOM();
    }
}
