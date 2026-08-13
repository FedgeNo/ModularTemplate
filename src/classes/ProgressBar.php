<?php

declare(strict_types=1);

class ProgressBar extends Progress
{
    public ?string $class = 'ProgressBar';
    public float $value = 0;
    public float $max = 100;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['value'] = (string) $this -> value;
        $this -> attributes['max'] = (string) $this -> max;

        return parent::toDOM();
    }
}
