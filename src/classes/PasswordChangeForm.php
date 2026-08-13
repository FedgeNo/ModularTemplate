<?php

declare(strict_types=1);

class PasswordChangeForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $current_password_words = (string) ($words['currentPassword'] ?? '');
        $confirm_password_words = (string) ($words['confirmPassword'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        $current_password = new InputField('currentPassword', $current_password_words, 'password', $current_password_words);
        $current_password -> labelVisible = true;
        $fields -> addContent($current_password);

        $new_password = new InputField('newPassword', (string) ($words['newPasswordLabel'] ?? ''), 'password', (string) ($words['newPasswordPlaceholder'] ?? ''));
        $new_password -> labelVisible = true;
        $new_password -> autocomplete = 'new-password';
        $fields -> addContent($new_password);

        $confirm_password = new InputField('confirmPassword', $confirm_password_words, 'password', $confirm_password_words);
        $confirm_password -> labelVisible = true;
        $confirm_password -> autocomplete = 'new-password';
        $fields -> addContent($confirm_password);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
