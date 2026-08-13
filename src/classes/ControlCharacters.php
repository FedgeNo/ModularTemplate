<?php

declare(strict_types=1);

/**
 * The characters no text on this site should carry: the C0 control codes, less
 * the three (tab, newline, carriage return) that are ordinary whitespace.
 *
 * They exist for terminals rather than for writing, nothing renders them, and
 * XML 1.0 cannot represent them at all - so one pasted into a post would make
 * the whole feed unparseable rather than spoil the one item. Stripped both as
 * text is stored (so what is kept is what can be shown) and as XML is written
 * (so text stored before that rule existed is covered too).
 */
class ControlCharacters
{
    private const PATTERN = '/[\x00-\x08\x0B\x0C\x0E-\x1F]/';

    public static function strip(string $text): string
    {
        return (string) preg_replace(self::PATTERN, '', $text);
    }
}
