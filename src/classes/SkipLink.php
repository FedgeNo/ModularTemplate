<?php

declare(strict_types=1);

/**
 * The way past the navigation, for somebody arriving by keyboard.
 *
 * The <main> landmark lets a screen reader jump to the content, but a sighted
 * keyboard user has no landmarks - without this they press Tab through the
 * whole navigation on every single page before reaching the first post. It is
 * the first thing in the document and out of sight until focused, which is the
 * one control on the site that is meant to be invisible until it is wanted.
 */
class SkipLink extends Anchor
{
    public ?string $class = 'SkipLink';

    public const TARGET = 'PageContent';

    public function __construct()
    {
        parent::__construct('#' . self::TARGET);
    }

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = (string) (Strings::for(self::class)['label'] ?? '');

        return parent::toDOM();
    }
}
