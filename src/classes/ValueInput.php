<?php

declare(strict_types=1);

/**
 * An <input> that carries a value attribute - i.e. every input type except
 * file. Text, email, password, hidden, radio and checkbox inputs all descend
 * from this; FileInput descends straight from Input, since the HTML spec forbids
 * a value attribute on <input type="file">.
 */
class ValueInput extends Input
{
    public string $value = '';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['value'] = $this -> value;

        return parent::toDOM();
    }
}
