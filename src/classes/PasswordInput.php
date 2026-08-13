<?php

declare(strict_types=1);

class PasswordInput extends ValueInput
{
    public function __construct()
    {
        parent::__construct();
        $this -> attributes['type'] = 'password';
    }
}
