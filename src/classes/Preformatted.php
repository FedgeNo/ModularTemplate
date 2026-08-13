<?php

declare(strict_types=1);

class Preformatted extends HTMLObject
{
    public string $tagName = 'pre';

    public function __construct(?string $text = null)
    {
        parent::__construct();

        if ($text !== null) {
            $this -> contents[] = $text;
        }
    }
}
