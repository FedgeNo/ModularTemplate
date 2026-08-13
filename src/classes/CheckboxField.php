<?php

declare(strict_types=1);

/**
 * A labeled checkbox form field - the checkbox followed by its visible label
 * (unlike InputField, whose label is visually hidden behind the placeholder,
 * a checkbox has no placeholder so the label carries the meaning).
 */
class CheckboxField extends Div
{
    public ?string $class = 'CheckboxField';

    public string $name;
    public string $label;
    public bool $checked = false;

    public function __construct(string $name, string $label)
    {
        parent::__construct();

        $this -> name = $name;
        $this -> label = $label;
    }

    public function toDOM(): \DOMElement
    {
        $checkbox = new CheckboxInput();
        $checkbox -> name = $this -> name;
        $checkbox -> id = $this -> name;
        $checkbox -> value = '1';

        if ($this -> checked) {
            $checkbox -> attributes['checked'] = 'checked';
        }

        $this -> contents[] = $checkbox;

        $label = new Label();
        $label -> for = $this -> name;
        $label -> contents[] = $this -> label;
        $this -> contents[] = $label;

        return parent::toDOM();
    }
}
