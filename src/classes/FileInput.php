<?php

declare(strict_types=1);

/**
 * A file input descends straight from Input rather than ValueInput: the HTML
 * spec forbids a value attribute on <input type="file"> (its value can only be
 * set by the user, never by markup), so it's the one input type without one.
 */
class FileInput extends Input
{
    public function __construct()
    {
        parent::__construct();
        $this -> attributes['type'] = 'file';
    }
}
