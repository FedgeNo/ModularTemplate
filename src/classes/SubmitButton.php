<?php

declare(strict_types=1);

/**
 * The control that submits a form, whatever the form is for - the label is the
 * only thing that varies between them. Its placement at the trailing edge of the
 * form belongs to .SubmitButton in the stylesheet rather than being composed
 * here, so the class attribute says what the element is and nothing else.
 */
class SubmitButton extends ButtonButton
{
    public function __construct(string $label)
    {
        parent::__construct();

        $this -> type = 'submit';
        $this -> contents[] = $label;
    }
}
