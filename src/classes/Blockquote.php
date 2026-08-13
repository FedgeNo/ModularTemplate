<?php

declare(strict_types=1);

class Blockquote extends HTMLObject
{
    public string $tagName = 'blockquote';

    public function __construct(?string $text = null)
    {
        parent::__construct();

        if ($text !== null) {
            $this -> contents[] = $text;
        }
    }
}
