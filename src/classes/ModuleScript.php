<?php

declare(strict_types=1);

class ModuleScript extends Script
{
    public function toDOM(): \DOMElement
    {
        $this -> attributes['type'] = 'module';
        return parent::toDOM();
    }
}
