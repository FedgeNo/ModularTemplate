<?php

declare(strict_types=1);

class EnvTest extends TestCase
{
    public function testStripQuotesRemovesMatchingDoubleQuotes(): void
    {
        $this -> assertSame('hello', Env::stripQuotes('"hello"'));
    }

    public function testStripQuotesRemovesMatchingSingleQuotes(): void
    {
        $this -> assertSame('hello', Env::stripQuotes("'hello'"));
    }

    public function testStripQuotesLeavesUnquotedValueAlone(): void
    {
        $this -> assertSame('hello', Env::stripQuotes('hello'));
    }

    public function testStripQuotesLeavesMismatchedQuotesAlone(): void
    {
        $this -> assertSame('"hello\'', Env::stripQuotes('"hello\''));
    }

    public function testStripQuotesLeavesOneSidedQuoteAlone(): void
    {
        $this -> assertSame('"hello', Env::stripQuotes('"hello'));
    }

    public function testStripQuotesLeavesShortStringAlone(): void
    {
        $this -> assertSame('"', Env::stripQuotes('"'));
        $this -> assertSame('', Env::stripQuotes(''));
    }

    public function testStripQuotesOnlyStripsOneOuterPair(): void
    {
        // A value that was already quoted once and got quoted again by
        // mistake keeps its inner pair - stripQuotes only ever removes one
        // layer, matching what Installer::envContents() relies on for a
        // stable round trip (see the class docblock).
        $this -> assertSame('"hello"', Env::stripQuotes('""hello""'));
    }
}
