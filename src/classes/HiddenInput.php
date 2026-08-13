<?php

declare(strict_types=1);

class HiddenInput extends ValueInput
{
    public function __construct()
    {
        parent::__construct();
        $this -> attributes['type'] = 'hidden';
    }
}
