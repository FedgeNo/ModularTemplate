<?php

declare(strict_types=1);

/**
 * The feeds are XML, and XML 1.0 cannot carry most control characters at all.
 * One in a single post's text would otherwise make the whole document
 * unparseable - every reader drops the entire feed, not just that item.
 */
class XMLObjectTest extends TestCase
{
    private function documentFor(XMLObject $object): string
    {
        (new \ReflectionProperty(DOMObject::class, 'document')) -> setValue(null, new \DOMDocument('1.0', 'UTF-8'));

        $element = $object -> toDOM();
        DOMObject::currentDocument() -> appendChild($element);

        return (string) DOMObject::currentDocument() -> saveXML();
    }

    private function item(string $text): XMLObject
    {
        $item = new XMLObject();
        $item -> tagName = 'item';
        $item -> addContent($text);

        return $item;
    }

    public function testAControlCharacterDoesNotMakeTheFeedUnparseable(): void
    {
        $xml = $this -> documentFor($this -> item("hello\x07world"));

        $this -> assertTrue(simplexml_load_string($xml) !== false, 'the feed should still parse');
        $this -> assertTrue(str_contains($xml, 'helloworld'));
    }

    public function testTheWhitespaceXMLAllowsIsKept(): void
    {
        $xml = $this -> documentFor($this -> item("one\ttwo\nthree"));
        $parsed = simplexml_load_string($xml);

        $this -> assertTrue($parsed !== false, 'the feed should still parse');
        $this -> assertSame("one\ttwo\nthree", (string) $parsed);
    }

    public function testOrdinaryTextIsUntouchedAndStillEscaped(): void
    {
        $xml = $this -> documentFor($this -> item('rock & roll <not a tag>'));
        $parsed = simplexml_load_string($xml);

        $this -> assertTrue($parsed !== false, 'the feed should still parse');
        $this -> assertSame('rock & roll <not a tag>', (string) $parsed);
    }
}
