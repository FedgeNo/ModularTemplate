<?php

declare(strict_types=1);

class LoginForm extends FormForm
{
    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $identifier = (string) ($words['identifier'] ?? '');
        $password = (string) ($words['password'] ?? '');
        $submit = (string) ($words['submit'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new InputField('identifier', $identifier, 'text', $identifier, 255));
        $fields -> addContent(new InputField('password', $password, 'password', $password));

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton($submit);

        return parent::toDOM();
    }
}
