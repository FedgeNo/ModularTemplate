<?php

declare(strict_types=1);

/**
 * Words that are only for whoever is not looking at the screen.
 *
 * The site says a few things with shape and colour alone - the unread dots are
 * a coloured circle and nothing else - and a circle is silent to a screen
 * reader. This carries the words that circle means, out of sight but in the
 * accessibility tree.
 *
 * Not aria-label: this is a span with no role, and an aria-label on one is
 * ignored by much of what would need to read it.
 */
class HiddenLabel extends Span
{
    public ?string $class = 'HiddenLabel';

    public function __construct(string $text)
    {
        parent::__construct();

        $this -> contents[] = $text;
    }
}
