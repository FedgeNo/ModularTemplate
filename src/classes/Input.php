<?php

declare(strict_types=1);

/**
 * The base <input>: everything an input has regardless of type (a name). It
 * deliberately has no value - a file input can't carry one, so value lives on
 * ValueInput, which every value-bearing input type descends from instead.
 */
class Input extends HTMLVoidElement
{
    public string $tagName = 'input';
    public ?string $name = null;

    public function toDOM(): \DOMElement
    {
        if ($this -> name === '') {
            throw new Exception('Input name must not be an empty string');
        }

        if ($this -> name !== null) {
            $this -> attributes['name'] = $this -> name;
        }

        return parent::toDOM();
    }
}
