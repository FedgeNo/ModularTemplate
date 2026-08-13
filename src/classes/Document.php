<?php

declare(strict_types=1);

// The shared base of HTMLDocument and XMLDocument: a Document is the DOMObject
// at the root of a render that also knows the content type it's served as (set
// by the concrete document - HTMLDocument, XMLDocument, RSSFeed) and how to
// stream itself. Serialization differs by dialect, so each subclass implements
// __toString; send() flushes any buffered output, sets the content type, and
// echoes that serialization.
abstract class Document extends DOMObject
{
    public string $contentType = '';

    abstract public function __toString(): string;

    public function send(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $this -> contentType);
        echo $this;
    }
}
