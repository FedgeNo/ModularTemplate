<?php

declare(strict_types=1);

class PageTitle extends Heading1
{
    public ?string $class = 'PageTitle';

    public function __construct(string $title)
    {
        parent::__construct();

        $this -> contents[] = $title;
    }
}
