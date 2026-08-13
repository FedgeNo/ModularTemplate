<?php

declare(strict_types=1);

class ErrorList extends ItemList
{
    public ?string $class = 'ErrorList';
    public array $mixins = ['d-flex', 'flex-column', 'gap-1'];

    /**
     * @param string[] $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct();

        // Each error string becomes an <li> - ItemList does the wrapping.
        $this -> contents = $errors;
    }
}
