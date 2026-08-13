<?php

declare(strict_types=1);

/**
 * The first-run setup form.
 */
class SetupFormTest extends TestCase
{
    /**
     * Every other field here is prefilled with a guess, because every other
     * field has a guessable answer. The site's title doesn't: it's the admin's
     * name for their own site, and a prefilled one is what whoever is clicking
     * through accepts without reading - which is how a site ends up named
     * after the software running it.
     */
    public function testTheSiteTitleFieldOffersNoName(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $form = (new SetupForm()) -> toDOM();
        HTMLObject::currentDocument() -> appendChild($form);

        $title = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//input[@name="siteTitle"]', $form);

        $this -> assertSame(1, $title -> length);
        $this -> assertSame('', $title -> item(0) -> getAttribute('value'));
    }
}
