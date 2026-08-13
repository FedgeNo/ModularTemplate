<?php

declare(strict_types=1);

class Base extends HTMLVoidElement
{
    public string $tagName = 'base';
    public ?string $href = null;
    public ?string $target = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> href !== null) {
            $this -> attributes['href'] = $this -> href;
        }

        if ($this -> target !== null) {
            $this -> attributes['target'] = $this -> target;
        }

        return parent::toDOM();
    }
}
