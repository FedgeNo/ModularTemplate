<?php

declare(strict_types=1);

class Paragraph extends HTMLObject
{
    public string $tagName = 'p';

    public function __construct(?string $text = null)
    {
        parent::__construct();

        if ($text !== null) {
            $this -> contents[] = $text;
        }
    }
}
