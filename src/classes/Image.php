<?php

declare(strict_types=1);

class Image extends HTMLVoidElement
{
    public string $tagName = 'img';
    public ?string $src = null;
    public ?string $alt = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> src !== null) {
            $this -> attributes['src'] = $this -> src;
        }

        if ($this -> alt !== null) {
            $this -> attributes['alt'] = $this -> alt;
        }

        return parent::toDOM();
    }
}
