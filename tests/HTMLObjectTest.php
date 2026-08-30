<?php

declare(strict_types=1);

class HTMLObjectTest extends TestCase
{
    /**
     * toDOM() builds into the shared document the app stands up as it renders a
     * page; a unit test puts a bare one in its place, then attaches the element
     * so a document-wide XPath can reach it.
     */
    private function elementFor(HTMLObject $object): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $object -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return $element;
    }

    public function testRendersTagNameAndTextContent(): void
    {
        $element = $this -> elementFor(new Paragraph('hello world'));

        $this -> assertSame('p', $element -> tagName);
        $this -> assertSame('hello world', $element -> textContent);
    }

    public function testRendersClassAttributeWhenSet(): void
    {
        $div = new Div();
        $div -> class = 'Card';
        $div -> addContent('content');

        $this -> assertSame('Card', $this -> elementFor($div) -> getAttribute('class'));
    }

    public function testOmitsClassAttributeWhenNull(): void
    {
        $div = new Div();
        $div -> addContent('content');

        $this -> assertFalse($this -> elementFor($div) -> hasAttribute('class'));
    }

    public function testNestsChildHTMLObjects(): void
    {
        $div = new Div();
        $div -> addContent(new Paragraph('inner'));

        $element = $this -> elementFor($div);
        $children = new \DOMXPath(HTMLObject::currentDocument()) -> query('./p', $element);

        $this -> assertSame(1, $children -> length);
        $this -> assertSame('inner', $children -> item(0) -> textContent);
    }

    public function testTextContentIsEscaped(): void
    {
        // createTextNode(), not raw markup - the same reason the whole app never
        // uses innerHTML on the JS side. The literal "<script>" is inert text,
        // so no such element exists in the tree, and the whole string survives
        // verbatim as the paragraph's text.
        $script = '<script>alert(1)</script>';
        $element = $this -> elementFor(new Paragraph($script));

        $this -> assertSame(0, new \DOMXPath(HTMLObject::currentDocument()) -> query('.//script', $element) -> length);
        $this -> assertSame($script, $element -> textContent);
    }

    public function testCustomAttributesAreRendered(): void
    {
        $div = new Div();
        $div -> attributes['data-user-id'] = '42';
        $div -> addContent('x');

        $this -> assertSame('42', $this -> elementFor($div) -> getAttribute('data-user-id'));
    }

    public function testAClassBeneathAPrimitiveNamesOnlyItself(): void
    {
        // Notice extends Paragraph, which is a primitive and names nothing, so
        // the chain begins and ends at Notice.
        $notice = new Notice('careful now');
        $this -> elementFor($notice);

        $this -> assertSame('Notice', $notice -> class);
    }

    public function testEveryLevelFromTheFirstNamedAncestorDownContributes(): void
    {
        $this -> assertSame('ChainBase ChainMiddle ChainLeafFake', $this -> elementFor(new ChainLeafFake()) -> getAttribute('class'));
    }

    public function testALevelMayRenameItselfWithoutSeveringTheChain(): void
    {
        // ChainMiddleFake declares a name of its own, the way ButtonButton
        // contributes "Button" - it replaces its own level and nothing else, so
        // what it inherited is still in front of it and what extends it still
        // follows.
        $rendered = $this -> elementFor(new ChainLeafFake()) -> getAttribute('class');

        $this -> assertTrue(str_starts_with($rendered, 'ChainBase '), 'the inherited name should still lead');
        $this -> assertFalse(str_contains($rendered, 'ChainMiddleFake'), 'the renamed level should contribute its declared name, not its class name');
    }

    public function testAPrimitiveThatNamesNothingAnywhereInItsChainGetsNoClass(): void
    {
        $this -> assertFalse($this -> elementFor(new Div()) -> hasAttribute('class'));
    }

    public function testGenericWrapperGetsNoClassAttribute(): void
    {
        // Div/Button/Anchor etc. never declare their own $class - per
        // HTMLObject's constructor, a class that doesn't redeclare $class
        // itself (declaring_class === HTMLObject) is treated as a generic
        // primitive and gets no automatic class name.
        $div = new Div();

        $this -> assertNull($div -> class);
    }
}

/** Three levels: one names itself, one renames its own level, one names nothing. */
class ChainBaseFake extends Div
{
    public ?string $class = 'ChainBase';
}

class ChainMiddleFake extends ChainBaseFake
{
    public ?string $class = 'ChainMiddle';
}

class ChainLeafFake extends ChainMiddleFake
{
}
