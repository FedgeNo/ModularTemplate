<?php

declare(strict_types=1);

class EmailChangeForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $new_email_words = (string) ($words['newEmail'] ?? '');
        $current_password_words = (string) ($words['currentPassword'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        // The browser's saved address is the one being replaced, so offering it
        // back fills the field with the wrong answer.
        $new_email = new InputField('newEmail', $new_email_words, 'email', $new_email_words, 255);
        $new_email -> autocomplete = 'new-password';
        $new_email -> labelVisible = true;
        $fields -> addContent($new_email);

        $current_password = new InputField('currentPassword', $current_password_words, 'password', $current_password_words);
        $current_password -> labelVisible = true;
        $fields -> addContent($current_password);

        $this -> contents[] = $fields;

        $this -> contents[] = new Notice((string) ($words['notice'] ?? ''));

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
