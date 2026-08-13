<?php

declare(strict_types=1);

/**
 * A collapsible settings group: a <details> whose <summary> is the section's
 * heading and whose body is the form (or status widget) it wraps. Lets the
 * User, Mod and Admin Settings pages stack as disclosures the reader opens one
 * at a time rather than one long always-open column.
 */
class SettingsSection extends Details
{
    public ?string $class = 'SettingsSection';

    private string $heading;
    private HTMLObject $body;

    public function __construct(string $heading, HTMLObject $body)
    {
        parent::__construct();

        $this -> heading = $heading;
        $this -> body = $body;
    }

    public function toDOM(): \DOMElement
    {
        $summary = new Summary();
        $summary -> contents[] = $this -> heading;

        $this -> contents[] = $summary;
        $this -> contents[] = $this -> body;

        return parent::toDOM();
    }
}
