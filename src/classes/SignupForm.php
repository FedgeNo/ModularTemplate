<?php

declare(strict_types=1);

class SignupForm extends FormForm
{
    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $display_name_words = (string) ($words['displayName'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));

        // Autocomplete hints mark this as a registration form: 'new-password'
        // tells the browser this isn't a login, so it stops autofilling saved
        // credentials over the placeholders (and offers to save the new ones).
        $username = new InputField('username', (string) ($words['usernameLabel'] ?? ''), 'text', (string) ($words['usernamePlaceholder'] ?? ''), User::MAX_USERNAME_LENGTH);
        $username -> autocomplete = 'username';
        $fields -> addContent($username);

        // Filled in by main.js as the name is typed. Empty (and so invisible)
        // until there's something to report, and announced politely so a
        // screen reader hears the verdict without it interrupting typing.
        $availability = new UsernameAvailability();
        $availability -> attributes['aria-live'] = 'polite';
        $fields -> addContent($availability);

        $email = new InputField('email', (string) ($words['emailLabel'] ?? ''), 'email', (string) ($words['emailPlaceholder'] ?? ''), 255);
        $email -> autocomplete = 'email';
        $fields -> addContent($email);

        $display_name = new InputField('displayName', $display_name_words, 'text', $display_name_words, 50);
        $display_name -> autocomplete = 'nickname';
        $fields -> addContent($display_name);

        $password = new InputField('password', (string) ($words['passwordLabel'] ?? ''), 'password', (string) ($words['passwordPlaceholder'] ?? ''));
        $password -> autocomplete = 'new-password';
        $fields -> addContent($password);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
