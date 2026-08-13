<?php

declare(strict_types=1);

class Heading2 extends HTMLObject
{
    public string $tagName = 'h2';

    public function __construct(?string $text = null)
    {
        parent::__construct();

        if ($text !== null) {
            $this -> contents[] = $text;
        }
    }
}
