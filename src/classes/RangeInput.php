<?php

declare(strict_types=1);

/**
 * An <input type="range">. Carries its own bounds, since a slider without them
 * is meaningless - the browser would silently assume 0-100.
 */
class RangeInput extends ValueInput
{
    public function __construct(int $min, int $max, int $value)
    {
        parent::__construct();

        $this -> attributes['type'] = 'range';
        $this -> attributes['min'] = (string) $min;
        $this -> attributes['max'] = (string) $max;
        $this -> value = (string) $value;
    }
}
