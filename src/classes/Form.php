<?php

declare(strict_types=1);

class Form extends HTMLObject
{
    public string $tagName = 'form';
    public ?string $action = null;
    public ?string $method = null;   // null = no method attribute
    public ?string $enctype = null;

    public function toDOM(): \DOMElement
    {
        // Validate method only if one is explicitly given
        if ($this -> method !== null) {
            $valid_methods = ['GET', 'POST'];

            if (!in_array(strtoupper($this -> method), $valid_methods, true)) {
                throw new Exception('Invalid form method: ' . $this -> method);
            }

            $this -> attributes['method'] = strtoupper($this -> method);
        }

        if ($this -> action !== null) {
            $this -> attributes['action'] = $this -> action;
        }

        if ($this -> enctype !== null) {
            $this -> attributes['enctype'] = $this -> enctype;
        }

        // Add CSRF token only when method is POST
        if ($this -> method !== null && strtoupper($this -> method) === 'POST') {
            $csrf_input = new HiddenInput();
            $csrf_input -> name = 'CSRFToken';
            $csrf_input -> value = CSRF::token();
            $this -> contents[] = $csrf_input;
        }

        return parent::toDOM();
    }
}
