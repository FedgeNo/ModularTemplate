<?php

declare(strict_types=1);

// The HTML layer over DOMObject: an app concept whose element carries a CSS
// class derived from its class hierarchy, guards against rendering twice, and
// accepts CData (and raw DOMNodes) as content on top of the strings and nested
// objects DOMObject already handles.
//
// Abstract, and it deliberately sets no tagName: every element either names its
// own tag or inherits one from a primitive that does (Div, Span, Button, ...).
// A default of 'div' here would silently make anything that forgot into a div,
// which is how seventeen classes ended up redeclaring a tag they already had.
// DOMObject's uninitialized $tagName is what turns forgetting into an error.
abstract class HTMLObject extends DOMObject
{
    public ?string $id = null;

    // What this element IS. Chained down the class hierarchy by
    // deriveClassName(), so a subclass's element carries its ancestors' names
    // too (ImageItem -> "FeedItem ImageItem").
    public ?string $class = null;

    // What this element is composed WITH: shared visual bundles (Card) and
    // layout utilities (d-flex, gap-2). Deliberately NOT chained - a subclass
    // inherits these only by ordinary property inheritance and can replace them
    // outright, so a child isn't forced into its parent's layout the way it
    // would be if these were welded into $class.
    //
    // This is where a concept like Card lives: PHP allows one parent, so a Form
    // that looks like a card can't extend Card, and before this the only way to
    // say it was to hardcode the name into $class in dozens of places.
    /** @var string[] */
    public array $mixins = [];

    // Set by the first output step - toDOM() or toJSON() - and a second one
    // throws. Output is one-shot because most objects build their children into
    // $this->contents in toDOM(), so a second call would duplicate them, and
    // plenty of output methods run a query or other non-idempotent work
    // besides. Reuse means building a fresh instance.
    private bool $rendered = false;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);
    }

    /**
     * Sets the element's CSS class from the class hierarchy: an app concept's
     * element carries its PHP class name(s) (e.g. ImageItem -> "FeedItem
     * ImageItem"), built from whichever ancestor first declared $class down to
     * static::class. A generic HTML-primitive wrapper (Div, Button, ...) that
     * never overrode $class keeps null - it isn't an app concept.
     */
    private function deriveClassName(): void
    {
        // Every level from the first ancestor that names itself down to this
        // class contributes one name. A level that declares $class contributes
        // that value instead of its own class name - which is how ButtonButton
        // contributes "Button" - and doing so adds to the chain rather than
        // replacing what sits above it, so an intermediate can rename its own
        // level without severing the identity it inherited.
        $levels = [];

        for ($class = static::class; $class !== self::class && $class !== false; $class = get_parent_class($class)) {
            $declares = (new \ReflectionProperty($class, 'class')) -> getDeclaringClass() -> getName() === $class;

            $levels[] = [
                'name' => $class,
                'declared' => $declares ? (new \ReflectionClass($class)) -> getDefaultProperties()['class'] : null,
            ];
        }

        // Walked bottom-up, so the last declaration found is the least derived
        // one, and the chain starts there. Nothing naming itself anywhere means
        // a generic HTML-primitive wrapper (Div, Button, Anchor, ...), which
        // isn't an app concept and gets no class name at all.
        $first = null;

        foreach ($levels as $index => $level) {
            if ($level['declared'] !== null) {
                $first = $index;
            }
        }

        if ($first === null) {
            return;
        }

        $names = [];

        for ($index = $first; $index >= 0; $index--) {
            $names[] = $levels[$index]['declared'] ?? $levels[$index]['name'];
        }

        // Anything on the property that isn't this class's declared default was
        // put there at runtime - a state a render decides on (Own, Encrypted)
        // or a second identity composed at the call site. The chain replaces the
        // declared identity only; the rest is carried through, because this runs
        // inside toDOM() and a plain assignment would silently discard whatever
        // the object had just set on itself.
        $declared = (new \ReflectionClass(static::class)) -> getDefaultProperties()['class'] ?? '';

        // The chain itself is excluded as well as the declared default, so
        // deriving twice cannot mistake what it wrote last time for something
        // the object added.
        $added = array_values(array_diff(
            array_filter(explode(' ', (string) $this -> class)),
            array_filter(explode(' ', (string) $declared)),
            $names
        ));

        $this -> class = implode(' ', array_merge($names, $added));
    }

    public function addContent(HTMLObject|CData|string|\DOMNode $item): void
    {
        $this -> contents[] = $item;
    }

    public function toDOM(): \DOMElement
    {
        $this -> deriveClassName();
        $this -> markRendered();

        $element = self::$document -> createElement($this -> tagName);

        if ($this -> id !== null) {
            $element -> setAttribute('id', $this -> id);
        }

        // Identity first, then what it's composed with, so the attribute reads
        // as "what this is, then how it looks".
        $class_names = array_filter(array_merge([$this -> class], $this -> mixins), static fn (?string $name): bool => $name !== null && $name !== '');

        if ($class_names !== []) {
            $element -> setAttribute('class', implode(' ', $class_names));
        }

        $this -> applyAttributes($element);

        foreach ($this -> contents as $item) {
            $node = $this -> contentToNode($item);
            if ($node !== null) {
                $element -> appendChild($node);
            }
        }

        return $element;
    }

    /**
     * Claims this object's one output step. Any method that turns the object
     * into something for a client - markup, a payload - calls this first, so
     * whichever runs, it runs once.
     */
    protected function markRendered(): void
    {
        if ($this -> rendered) {
            throw new \LogicException(static::class . ' produced output twice - build a fresh instance per output step; a rendered HTMLObject is not reusable.');
        }

        $this -> rendered = true;
    }

    protected function contentToNode($item): ?\DOMNode
    {
        if ($item instanceof HTMLObject or $item instanceof CData) {
            return $item -> toDOM();
        } elseif (is_string($item)) {
            return self::$document -> createTextNode($item);
        } elseif ($item instanceof \DOMNode) {
            return self::$document -> importNode($item, true);
        }

        return null;
    }
}
