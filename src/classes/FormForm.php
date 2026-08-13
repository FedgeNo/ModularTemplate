<?php

declare(strict_types=1);

// A Form that contributes its own name to the shared identity chain: a subclass
// that doesn't override $class itself renders as "Form <ClassName>"
// automatically (and "Form <Middle> <ClassName>" through an abstract middle
// class), so the look every form shares hangs off one selector instead of each
// form repeating it. A form with no identity of its own, or one that
// deliberately skips the shared styling, extends Form directly instead.
class FormForm extends Form
{
    public ?string $class = 'Form';

    // POST by default so that a form submitted without JavaScript (the
    // handlers all intercept submit) never falls back to a native GET with
    // its fields - passwords among them - in the query string and the server
    // log. Form adds the CSRF token to every POST form. A form that really
    // is a GET form overrides this.
    public ?string $method = 'POST';
}
