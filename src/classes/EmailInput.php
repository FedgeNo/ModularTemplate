<?php

declare(strict_types=1);

class EmailInput extends ValueInput
{
    public function __construct()
    {
        parent::__construct();
        $this -> attributes['type'] = 'email';
    }
}
