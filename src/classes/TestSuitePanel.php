<?php

declare(strict_types=1);

class TestSuitePanel extends Div
{
    public ?string $class = 'TestSuitePanel';

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $this -> addContent(new Paragraph((string) ($words['intro'] ?? '')));

        $link = new Anchor(ServerURL::absolute('/admin/tests'), (string) ($words['runLabel'] ?? ''));
        $link -> class = 'Button';
        $this -> addContent($link);

        return parent::toDOM();
    }
}
