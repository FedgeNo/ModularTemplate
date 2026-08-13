<?php

declare(strict_types=1);

class RadioInput extends ValueInput
{
    public function __construct()
    {
        parent::__construct();
        $this -> attributes['type'] = 'radio';
    }
}
