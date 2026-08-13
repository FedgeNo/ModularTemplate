<?php

declare(strict_types=1);

class Time extends HTMLObject
{
    public string $tagName = 'time';
    public ?string $datetime = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> datetime !== null) {
            $this -> attributes['datetime'] = $this -> datetime;
        }

        return parent::toDOM();
    }
}
