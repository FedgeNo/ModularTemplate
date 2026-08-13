<?php

declare(strict_types=1);

class Video extends HTMLObject
{
    public string $tagName = 'video';
    public ?string $class = 'Video';
    public ?string $src = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> src !== null) {
            $this -> attributes['src'] = $this -> src;
        }

        return parent::toDOM();
    }
}
