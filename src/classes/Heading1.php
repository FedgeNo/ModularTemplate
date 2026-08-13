<?php

declare(strict_types=1);

class Heading1 extends HTMLObject
{
    public string $tagName = 'h1';

    public function __construct(?string $text = null)
    {
        parent::__construct();

        if ($text !== null) {
            $this -> contents[] = $text;
        }
    }
}
