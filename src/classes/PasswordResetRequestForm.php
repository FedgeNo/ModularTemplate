<?php

declare(strict_types=1);

class PasswordResetRequestForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $email_label = (string) ($words['emailLabel'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new InputField('email', $email_label, 'email', $email_label, 255));
        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
