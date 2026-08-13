<?php

declare(strict_types=1);

class ControlCharactersTest extends TestCase
{
    public function testTheControlCodesXMLCannotCarryAreRemoved(): void
    {
        $this -> assertSame('helloworld', ControlCharacters::strip("hello\x07world"));
        $this -> assertSame('ab', ControlCharacters::strip("a\x00\x01\x08\x0B\x0C\x1Fb"));
    }

    /**
     * Tab, newline and carriage return are ordinary whitespace, and a post's
     * line breaks are exactly what a newline is doing there.
     */
    public function testOrdinaryWhitespaceSurvives(): void
    {
        $this -> assertSame("one\ttwo\nthree\r\nfour", ControlCharacters::strip("one\ttwo\nthree\r\nfour"));
    }

    public function testTextAndEmojiAreUntouched(): void
    {
        $this -> assertSame('café ☕ 数学 — ok', ControlCharacters::strip('café ☕ 数学 — ok'));
    }

    /**
     * The post body is stored as a Delta, so a control character has to be
     * gone from the ops as well as from the plaintext derived off them -
     * otherwise the feed reads clean while the stored post still carries it.
     */
}
