<?php

declare(strict_types=1);

class TestSuitePanel extends Div
{
    public ?string $class = 'TestSuitePanel';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2', 'align-items-start'];

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
