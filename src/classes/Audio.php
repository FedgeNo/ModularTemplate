<?php

declare(strict_types=1);

class Audio extends HTMLObject
{
    public string $tagName = 'audio';
    public ?string $class = 'Audio';
    public ?string $src = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> src !== null) {
            $this -> attributes['src'] = $this -> src;
        }

        return parent::toDOM();
    }
}
