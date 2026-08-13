<?php

declare(strict_types=1);

/**
 * The web setup wizard's "setup finished" checklist - the manual steps that
 * remain after .env has been written, in the order to do them.
 */
class SetupNextSteps extends OrderedList
{
    public ?string $class = 'SetupNextSteps';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $steps = [
            'Restore the project root\'s normal permissions (it was made web-server-writable so this step could write .env): run `chmod 755 ' . realpath(__DIR__ . '/../..') . '` on the server.',
            'Reload this page and sign up - the first account created becomes the site\'s administrator.',
        ];

        foreach ($steps as $step) {
            $item = new ListItem();
            $item -> contents[] = $step;
            $this -> contents[] = $item;
        }

        return parent::toDOM();
    }
}
