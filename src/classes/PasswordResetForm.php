<?php

declare(strict_types=1);

class PasswordResetForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];
    public string $token;

    public function __construct(string $token)
    {
        parent::__construct();

        $this -> token = $token;
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $token_input = new HiddenInput();
        $token_input -> name = 'token';
        $token_input -> value = $this -> token;
        $this -> contents[] = $token_input;

        $confirm_password_label = (string) ($words['confirmPasswordLabel'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new InputField(
            'newPassword',
            (string) ($words['newPasswordLabel'] ?? ''),
            'password',
            (string) ($words['newPasswordPlaceholder'] ?? '')
        ));
        $fields -> addContent(new InputField('confirmPassword', $confirm_password_label, 'password', $confirm_password_label));
        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
