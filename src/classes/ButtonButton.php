<?php

declare(strict_types=1);

// A Button that contributes its own name to the shared identity chain: a
// subclass that doesn't override $class itself renders as "Button
// <ClassName>" automatically via deriveClassName(), instead of every
// constructor repeating both the class name and the 'Button' mixin. A button
// with no identity of its own, a mismatched identity, or that deliberately
// skips the shared styling extends Button directly instead.
class ButtonButton extends Button
{
    public ?string $class = 'Button';

    /**
     * Gives a button that shows no words a name anyway.
     *
     * A core action is an emoji and nothing else, which is a picture with no
     * accessible name at all - a screen reader would announce "button" and
     * leave it there. The name is also the tooltip, since a picture is only
     * obvious to whoever already knows it.
     */
    protected function nameIt(string $name): void
    {
        $this -> attributes['aria-label'] = $name;
        $this -> attributes['title'] = $name;
    }

    /**
     * Whether this toggle is currently on, for assistive tech. The visible
     * difference is the colour and, where the emoji has a natural pair, the
     * glyph - neither of which a screen reader sees.
     */
    protected function pressed(bool $on): void
    {
        $this -> attributes['aria-pressed'] = $on ? 'true' : 'false';
    }
}
