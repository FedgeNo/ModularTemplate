<?php

declare(strict_types=1);

// The XML counterpart of HTMLDocument: it stands up the DOMDocument the tree
// renders into and serializes it whole, so the XML declaration leads the
// output. Its element is the XML root - RSSFeed's is <rss>. contentType is the
// generic XML type; a subclass tied to a specific dialect overrides it.
class XMLDocument extends Document
{
    public string $contentType = 'application/xml; charset=UTF-8';

    public function toDOM(): \DOMElement
    {
        self::$document = new \DOMDocument('1.0', 'UTF-8');
        self::$document -> formatOutput = true;

        return parent::toDOM();
    }

    public function __toString(): string
    {
        $root = $this -> toDOM();
        self::$document -> appendChild($root);

        return self::$document -> saveXML();
    }
}
