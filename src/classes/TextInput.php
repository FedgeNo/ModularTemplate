<?php

declare(strict_types=1);

class TextInput extends ValueInput
{
    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);
        $this -> attributes['type'] = 'text';
    }
}
