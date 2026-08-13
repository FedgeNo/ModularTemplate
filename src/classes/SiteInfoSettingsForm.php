<?php

declare(strict_types=1);

/**
 * The Admin Settings form for one editable site-info text (the About
 * overview, Terms of Service, or Privacy Policy): a textarea prefilled with
 * the current text (shipped default included, so the admin edits from
 * something rather than a blank box) and a save button. One descendant per
 * info text.
 */
abstract class SiteInfoSettingsForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    /** The POST field / Settings name this form edits. */
    protected string $settingName = '';

    abstract protected function currentText(): string;

    public function toDOM(): \DOMElement
    {
        // static:: rather than self:: - toDOM() lives here once, but each
        // descendant (About/Terms/Privacy) says its own legend and
        // description, so the lookup has to follow the concrete class.
        $words = Strings::for(static::class);
        $legend = (string) ($words['legend'] ?? '');

        $fields = new Fieldset($legend);

        $textarea = new TextareaField($this -> settingName, $legend, null, 65535);
        $textarea -> value = $this -> currentText();
        $fields -> addContent($textarea);

        $this -> contents[] = $fields;

        $this -> contents[] = new Notice((string) ($words['description'] ?? ''));

        $this -> contents[] = new SubmitButton((string) ($words['save'] ?? ''));

        return parent::toDOM();
    }
}
