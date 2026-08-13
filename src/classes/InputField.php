<?php

declare(strict_types=1);

class InputField extends Div
{
    public ?string $class = 'InputField';

    public string $name;
    public string $label;
    public string $type = 'text';
    public ?string $placeholder = null;
    public string $value = '';
    public ?int $maxLength = null;
    public ?string $autocomplete = null;
    public bool $labelVisible = true;

    /**
     * Why this input was refused, shown under it.
     *
     * Set where the server already knows before the page is drawn. The client
     * puts the same element in the same place when an endpoint answers with a
     * field it would not accept - see FormErrors.js, which has to agree with
     * what is built here.
     */
    public ?string $error = null;

    public function __construct(string $name, string $label, string $type = 'text', ?string $placeholder = null, ?int $max_length = null)
    {
        parent::__construct();

        $this -> name = $name;
        $this -> label = $label;
        $this -> type = $type;
        $this -> placeholder = $placeholder ?? $label;
        $this -> maxLength = $max_length;
    }

    public function toDOM(): \DOMElement
    {
        $label = new Label();
        $label -> for = $this -> name;
        $label -> class = $this -> labelVisible ? null : 'visually-hidden';
        $label -> contents[] = $this -> label;
        $this -> contents[] = $label;

        $input = self::inputForType($this -> type);
        $input -> name = $this -> name;
        $input -> id = $this -> name;
        $input -> value = $this -> value;
        $input -> attributes['placeholder'] = $this -> placeholder;

        if ($this -> maxLength !== null) {
            $input -> attributes['maxlength'] = (string) $this -> maxLength;
        }

        if ($this -> autocomplete !== null) {
            $input -> attributes['autocomplete'] = $this -> autocomplete;
        }

        if ($this -> error !== null) {
            $input -> attributes['aria-invalid'] = 'true';
            $input -> attributes['aria-describedby'] = $this -> name . 'Error';
        }

        $this -> contents[] = $input;

        if ($this -> error !== null) {
            $this -> contents[] = self::errorElement($this -> name, $this -> error);
        }

        return parent::toDOM();
    }

    /**
     * The reason under a refused input, built the one way so the server and
     * the client cannot drift into showing it differently.
     */
    public static function errorElement(string $name, string $reason): Paragraph
    {
        $error = new Paragraph();
        $error -> class = 'FieldError';
        $error -> attributes['id'] = $name . 'Error';
        $error -> contents[] = $reason;

        return $error;
    }

    protected static function inputForType(string $type): Input
    {
        return match ($type) {
            'email' => new EmailInput(),
            'password' => new PasswordInput(),
            default => new TextInput(),
        };
    }
}
